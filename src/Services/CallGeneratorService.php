<?php

namespace UWMadison\CallLog\Services;

use REDCap;
use Project;
use UWMadison\CallLog\CallMetadataRepository;
use UWMadison\CallLog\CallTemplateType;
use UWMadison\CallLog\CallItemDTO;

class CallGeneratorService
{
    private $module;
    private $configService;
    private $metadataRepo;
    private $dateMathService;
    private ?LoggingService $loggingService;

    public function __construct($module, ConfigService $configService, $metadataRepo, DateMathService $dateMathService, ?LoggingService $loggingService = null)
    {
        $this->module = $module;
        $this->configService = $configService;
        $this->metadataRepo = $metadataRepo;
        $this->dateMathService = $dateMathService;
        $this->loggingService = $loggingService;
    }

    public function evaluateAndGenerateForRecord(int $projectId, string $record, ?string $savedInstrument = null, string $trigger = 'save_record'): bool
    {
        $metadata = $this->metadataRepo->getMetadata($projectId, $record);
        $config = $this->configService->getCallTemplateConfig($projectId);
        $rawSettings = $this->configService->getRawProjectSettings($projectId);
        $triggerForms = $rawSettings['trigger_save'] ?? [];

        $changes = [];
        $pendingLogs = [];

        $neededFields = [];
        foreach ($config['followup'] ?? [] as $cc) {
            if (!empty($cc['field'])) $neededFields[] = $cc['field'];
            if (!empty($cc['end'])) $neededFields[] = $cc['end'];
        }
        foreach ($config['reminder'] ?? [] as $cc) {
            if (!empty($cc['field'])) $neededFields[] = $cc['field'];
            if (!empty($cc['removeVar'])) $neededFields[] = $cc['removeVar'];
        }
        foreach ($config['mcv'] ?? [] as $cc) {
            if (!empty($cc['apptDate'])) $neededFields[] = $cc['apptDate'];
            if (!empty($cc['indicator'])) $neededFields[] = $cc['indicator'];
        }
        foreach ($config['nts'] ?? [] as $cc) {
            if (!empty($cc['apptDate'])) $neededFields[] = $cc['apptDate'];
            if (!empty($cc['indicator'])) $neededFields[] = $cc['indicator'];
            if (!empty($cc['skip'])) $neededFields[] = $cc['skip'];
        }
        foreach ($config['visit'] ?? [] as $cc) {
            if (!empty($cc['indicator'])) $neededFields[] = $cc['indicator'];
            if (!empty($cc['autoRemove'])) $neededFields[] = $cc['autoRemove'];
        }
        $neededFields = array_values(array_unique(array_filter($neededFields)));

        $recordData = !empty($neededFields)
            ? (REDCap::getData($projectId, 'array', $record, $neededFields)[$record] ?? [])
            : [];

        if (empty($triggerForms) || empty($savedInstrument) || in_array($savedInstrument, $triggerForms, true)) {
            $changes[] = $this->metadataFollowup($projectId, $record, $metadata, $config['followup'] ?? [], $trigger, $savedInstrument, $pendingLogs, $recordData);
            $changes[] = $this->metadataReminder($projectId, $record, $metadata, $config['reminder'] ?? [], $trigger, $savedInstrument, $pendingLogs, $recordData);
            $changes[] = $this->metadataMissedCancelled($projectId, $record, $metadata, $config['mcv'] ?? [], $trigger, $savedInstrument, $pendingLogs, $recordData);
            $changes[] = $this->metadataNeedToSchedule($projectId, $record, $metadata, $config['nts'] ?? [], $trigger, $savedInstrument, $pendingLogs, $recordData);
        }

        $changes[] = $this->metadataNewEntry($projectId, $record, $metadata, $config['new'] ?? [], $trigger, $savedInstrument, $pendingLogs);
        $changes[] = $this->metadataPhoneVisit($projectId, $record, $metadata, $config['visit'] ?? [], $trigger, $savedInstrument, $pendingLogs, $recordData);

        if (in_array(true, $changes, true)) {
            $saved = $this->metadataRepo->saveMetadata($projectId, $record, $metadata);
            if ($saved && $this->loggingService) {
                foreach ($pendingLogs as $item) {
                    $this->dispatchLog($projectId, $record, $item);
                }
            }
            return $saved;
        }

        return false;
    }

    public function evaluateAndGenerateForProject(int $projectId, string $trigger = 'batch'): int
    {
        $startTime = microtime(true);
        global $Proj;
        if (!isset($Proj) || (int)$Proj->project_id !== $projectId) {
            $Proj = new Project($projectId);
        }
        $recordIdField = !empty($Proj->table_pk) ? $Proj->table_pk : 'record_id';
        $records = REDCap::getData($projectId, 'json', null, [$recordIdField]);
        $recordsArray = json_decode($records, true);
        if (empty($recordsArray)) return 0;

        $recordIds = array_unique(array_column($recordsArray, $recordIdField));
        $generatedCount = 0;

        foreach ($recordIds as $record) {
            if ($this->evaluateAndGenerateForRecord($projectId, (string)$record, null, $trigger)) {
                $generatedCount++;
            }
        }

        $duration = microtime(true) - $startTime;
        if ($this->loggingService) {
            $this->loggingService->logBatchGenerationSummary(
                $projectId,
                $trigger,
                count($recordIds),
                $generatedCount,
                $duration
            );
        }

        return $generatedCount;
    }

    /**
     * Fast temporal lifecycle evaluator (hourly cron).
     * Focuses on time-sensitive transitions:
     * 1. Evaluates all records that currently have active/open call metadata (e.g. expiring reminders, MCV attendance completions).
     * 2. Evaluates records without metadata to pick up newly added/imported records for New Entry call generation.
     */
    public function evaluateTemporalLifecyclesForProject(int $projectId): int
    {
        $startTime = microtime(true);
        global $Proj;
        if (!isset($Proj) || (int)$Proj->project_id !== $projectId) {
            $Proj = new Project($projectId);
        }
        $recordIdField = !empty($Proj->table_pk) ? $Proj->table_pk : 'record_id';

        $table = REDCap::getDataTable($projectId);

        // 1. Records with existing metadata
        $sqlMeta = "SELECT DISTINCT record FROM {$table} WHERE project_id = ? AND field_name = 'call_metadata' AND value IS NOT NULL AND value != ''";
        $resMeta = $this->module->query($sqlMeta, [$projectId]);
        $targetRecords = [];
        while ($row = $resMeta->fetch_assoc()) {
            $targetRecords[] = (string)$row['record'];
        }

        // 2. Records without metadata (to generate New Entry calls quickly for new records)
        $config = $this->configService->getCallTemplateConfig($projectId);
        if (!empty($config['new'])) {
            $recordsJson = REDCap::getData($projectId, 'json', null, [$recordIdField]);
            $allRecords = json_decode($recordsJson, true);
            if (!empty($allRecords)) {
                $existingLookup = array_flip($targetRecords);
                foreach ($allRecords as $r) {
                    $rid = (string)($r[$recordIdField] ?? '');
                    if ($rid !== '' && !isset($existingLookup[$rid])) {
                        $targetRecords[] = $rid;
                    }
                }
            }
        }

        $targetRecords = array_values(array_unique($targetRecords));
        if (empty($targetRecords)) return 0;

        $updatedCount = 0;
        foreach ($targetRecords as $record) {
            if ($this->evaluateAndGenerateForRecord($projectId, $record, null, 'cron_temporal')) {
                $updatedCount++;
            }
        }

        $duration = microtime(true) - $startTime;
        if ($this->loggingService) {
            $this->loggingService->logBatchGenerationSummary(
                $projectId,
                'cron_temporal',
                count($targetRecords),
                $updatedCount,
                $duration
            );
        }

        return $updatedCount;
    }

    private function metadataNewEntry(int $projectId, string $record, array &$metadata, array $config, string $trigger, ?string $savedInstrument, array &$pendingLogs): bool
    {
        if (!empty($metadata)) return false;
        $changeOccurred = false;

        foreach ($config as $callConfig) {
            if (!empty($metadata[$callConfig['id']])) continue;
            $expireDays = (isset($callConfig['expire']) && $callConfig['expire'] !== null && $callConfig['expire'] !== '') ? (int)$callConfig['expire'] : null;
            $dto = new CallItemDTO([
                "id" => $callConfig['id'],
                "template" => CallTemplateType::NEW->value,
                "event_id" => '',
                "name" => $callConfig['name'],
                "load" => date("Y-m-d H:i"),
                "instances" => [],
                "voiceMails" => 0,
                "expire" => $expireDays,
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'] ?? 9999,
                "status" => "incomplete"
            ]);
            $metadata[$callConfig['id']] = $dto->toArray();
            $changeOccurred = true;

            $pendingLogs[] = [
                'type' => 'generated',
                'call_id' => $callConfig['id'],
                'call_name' => $callConfig['name'],
                'template' => 'new',
                'trigger' => $trigger,
                'details' => [
                    'trigger_instrument' => $savedInstrument ?? '',
                    'expire_days' => $expireDays
                ]
            ];
        }

        return $changeOccurred;
    }

    private function metadataFollowup(int $projectId, string $record, array &$metadata, array $config, string $trigger, ?string $savedInstrument, array &$pendingLogs, ?array $prefetchedData = null): bool
    {
        $changeOccurred = false;
        foreach ($config as $callConfig) {
            $data = $prefetchedData ?? (REDCap::getData($projectId, 'array', $record, [$callConfig['field'], $callConfig['end']])[$record] ?? []);
            $fieldVal = $data[$callConfig['event']][$callConfig['field']] ?? '';

            if (!empty($metadata[$callConfig['id']]) && empty($fieldVal)) {
                unset($metadata[$callConfig['id']]);
                $changeOccurred = true;
                $pendingLogs[] = [
                    'type' => 'removed',
                    'call_id' => $callConfig['id'],
                    'call_name' => $callConfig['name'],
                    'reason' => 'Follow-up trigger date cleared'
                ];
            } elseif (!empty($fieldVal)) {
                $start = date('Y-m-d', strtotime("{$fieldVal} +{$callConfig['days']} days"));
                $end = $data[$callConfig['event']][$callConfig['end']] ?? '';
                if (empty($end)) {
                    $endDays = $callConfig['days'] + $callConfig['length'];
                    $end = date('Y-m-d', strtotime("{$fieldVal} +{$endDays} days"));
                }

                if (empty($metadata[$callConfig['id']])) {
                    $metadata[$callConfig['id']] = [
                        "start" => $start,
                        "end" => $end,
                        "created" => date('Y-m-d H:i:s'),
                        "template" => 'followup',
                        "event_id" => $callConfig['event'],
                        "name" => $callConfig['name'],
                        "instances" => [],
                        "voiceMails" => 0,
                        "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                        "status" => "incomplete"
                    ];
                    $changeOccurred = true;

                    $pendingLogs[] = [
                        'type' => 'generated',
                        'call_id' => $callConfig['id'],
                        'call_name' => $callConfig['name'],
                        'template' => 'followup',
                        'trigger' => $trigger,
                        'details' => [
                            'start_date' => $start,
                            'end_date' => $end,
                            'trigger_field' => $callConfig['field'],
                            'trigger_date' => $fieldVal,
                            'trigger_instrument' => $savedInstrument ?? ''
                        ]
                    ];
                } else {
                    $oldStart = $metadata[$callConfig['id']]['start'] ?? '';
                    $oldEnd = $metadata[$callConfig['id']]['end'] ?? '';
                    if ($oldStart !== $start || $oldEnd !== $end) {
                        $metadata[$callConfig['id']]['start'] = $start;
                        $metadata[$callConfig['id']]['end'] = $end;
                        $changeOccurred = true;

                        $pendingLogs[] = [
                            'type' => 'updated',
                            'call_id' => $callConfig['id'],
                            'call_name' => $callConfig['name'],
                            'details' => [
                                'old_start' => $oldStart,
                                'new_start' => $start,
                                'old_end' => $oldEnd,
                                'new_end' => $end,
                                'trigger' => $trigger
                            ]
                        ];
                    }
                }
            }
        }

        return $changeOccurred;
    }

    private function metadataReminder(int $projectId, string $record, array &$metadata, array $config, string $trigger, ?string $savedInstrument, array &$pendingLogs, ?array $prefetchedData = null): bool
    {
        $changeOccurred = false;
        $today = date('Y-m-d');
        $rawSettings = $this->configService->getRawProjectSettings($projectId);
        $enabledHolidays = $rawSettings['enabled_holidays'] ?? null;
        $customDates = $rawSettings['custom_holidays_date'] ?? null;
        $customNames = $rawSettings['custom_holidays_name'] ?? null;

        foreach ($config as $callConfig) {
            $data = $prefetchedData ?? (REDCap::getData($projectId, 'array', $record, [$callConfig['field'], $callConfig['removeVar']])[$record] ?? []);
            $removeFlag = $data[$callConfig['removeEvent']][$callConfig['removeVar']] ?? false;

            if (!empty($metadata[$callConfig['id']]) && empty($metadata[$callConfig['id']]['instances']) && $removeFlag) {
                unset($metadata[$callConfig['id']]);
                $changeOccurred = true;
                $pendingLogs[] = [
                    'type' => 'removed',
                    'call_id' => $callConfig['id'],
                    'call_name' => $callConfig['name'],
                    'reason' => 'Reminder removal flag set'
                ];
                continue;
            }

            if ($removeFlag) continue;

            $fieldVal = $data[$callConfig['event']][$callConfig['field']] ?? '';

            $newStart = $this->dateMathService->dateMath($fieldVal, '-', $callConfig['days'], $enabledHolidays, $customDates, $customNames);
            $newEnd = $this->dateMathService->dateMath($fieldVal, '+', $callConfig['days'] == 0 ? 365 : 0, $enabledHolidays, $customDates, $customNames);

            if (!empty($metadata[$callConfig['id']]) && empty($fieldVal) && empty($metadata[$callConfig['id']]['instances'])) {
                unset($metadata[$callConfig['id']]);
                $changeOccurred = true;
                $pendingLogs[] = [
                    'type' => 'removed',
                    'call_id' => $callConfig['id'],
                    'call_name' => $callConfig['name'],
                    'reason' => 'Appointment date cleared'
                ];
            } elseif (!empty($metadata[$callConfig['id']]) && empty($fieldVal)) {
                if (($metadata[$callConfig['id']]['status'] ?? '') !== 'complete') {
                    $metadata[$callConfig['id']]["status"] = "complete";
                    $metadata[$callConfig['id']]["completedBy"] = "REDCap";
                    $changeOccurred = true;
                    $pendingLogs[] = [
                        'type' => 'auto_completed',
                        'call_id' => $callConfig['id'],
                        'call_name' => $callConfig['name'],
                        'reason' => 'Appointment date cleared after call instances logged'
                    ];
                }
            } elseif (!empty($metadata[$callConfig['id']]) && !empty($fieldVal) && ($fieldVal <= $today)) {
                if (($metadata[$callConfig['id']]['status'] ?? '') !== 'complete' && ($metadata[$callConfig['id']]['status'] ?? '') !== 'expired') {
                    $metadata[$callConfig['id']]['status'] = 'expired';
                    $metadata[$callConfig['id']]["completedBy"] = "REDCap";
                    $changeOccurred = true;
                    $pendingLogs[] = [
                        'type' => 'auto_completed',
                        'call_id' => $callConfig['id'],
                        'call_name' => $callConfig['name'],
                        'reason' => 'Appointment date reached or passed without completion (' . $fieldVal . ')'
                    ];
                }
            } elseif (!empty($metadata[$callConfig['id']]) && !empty($fieldVal) && (($metadata[$callConfig['id']]['start'] ?? '') !== $newStart || ($metadata[$callConfig['id']]['end'] ?? '') !== $newEnd)) {
                $oldStart = $metadata[$callConfig['id']]['start'] ?? '';
                $oldEnd = $metadata[$callConfig['id']]['end'] ?? '';
                $metadata[$callConfig['id']]['status'] = 'incomplete';
                $metadata[$callConfig['id']]['start'] = $newStart;
                $metadata[$callConfig['id']]['end'] = $newEnd;
                $metadata[$callConfig['id']]['appt'] = $fieldVal;
                $changeOccurred = true;

                $pendingLogs[] = [
                    'type' => 'updated',
                    'call_id' => $callConfig['id'],
                    'call_name' => $callConfig['name'],
                    'details' => [
                        'old_start' => $oldStart,
                        'new_start' => $newStart,
                        'old_end' => $oldEnd,
                        'new_end' => $newEnd,
                        'appt_date' => $fieldVal,
                        'trigger' => $trigger
                    ]
                ];
            } elseif (empty($metadata[$callConfig['id']]) && !empty($fieldVal)) {
                $metadata[$callConfig['id']] = [
                    "start" => $newStart,
                    "end" => $newEnd,
                    "appt" => $fieldVal,
                    "created" => date('Y-m-d H:i:s'),
                    "template" => 'reminder',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "status" => "incomplete"
                ];
                $changeOccurred = true;

                $pendingLogs[] = [
                    'type' => 'generated',
                    'call_id' => $callConfig['id'],
                    'call_name' => $callConfig['name'],
                    'template' => 'reminder',
                    'trigger' => $trigger,
                    'details' => [
                        'start_date' => $newStart,
                        'end_date' => $newEnd,
                        'appt_date' => $fieldVal,
                        'trigger_instrument' => $savedInstrument ?? ''
                    ]
                ];
            }
        }

        return $changeOccurred;
    }

    private function metadataMissedCancelled(int $projectId, string $record, array &$metadata, array $config, string $trigger, ?string $savedInstrument, array &$pendingLogs, ?array $prefetchedData = null): bool
    {
        $changeOccurred = false;
        $today = date('Y-m-d');
        foreach ($config as $callConfig) {
            $data = ($prefetchedData ?? (REDCap::getData($projectId, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator']])[$record] ?? []))[$callConfig['event']] ?? [];
            $apptDate = $data[$callConfig['apptDate']] ?? '';
            $indicator = $data[$callConfig['indicator']] ?? '';
            $idExact = $callConfig['id'] . '||' . $apptDate;

            $apptDateOnly = !empty($apptDate) ? explode(' ', $apptDate)[0] : '';
            $apptPassed = !empty($apptDateOnly) && ($apptDateOnly < $today);
            $shouldGenerate = !empty($apptDate) && (!empty($indicator) || $apptPassed);

            if (empty($metadata[$idExact]) && $shouldGenerate) {
                $reason = !empty($indicator)
                    ? 'Missed/cancelled indicator flagged'
                    : 'Appointment date passed without attendance confirmation (' . $apptDate . ')';

                $metadata[$idExact] = [
                    "appt" => $apptDate,
                    "created" => date('Y-m-d H:i:s'),
                    "template" => 'mcv',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "status" => "incomplete"
                ];
                $changeOccurred = true;

                $pendingLogs[] = [
                    'type' => 'generated',
                    'call_id' => $idExact,
                    'call_name' => $callConfig['name'],
                    'template' => 'mcv',
                    'trigger' => $trigger,
                    'details' => [
                        'appt_date' => $apptDate,
                        'indicator_val' => $indicator,
                        'generation_reason' => $reason,
                        'trigger_instrument' => $savedInstrument ?? ''
                    ]
                ];
            } elseif (!empty($metadata[$idExact])) {
                // Determine auto-completion:
                // 1. If indicator was flagged as missed and is now cleared while appt is not in the past
                // 2. If indicator represents attendance confirmation and is now set (attended)
                $isAttendedConfirmation = $apptPassed && !empty($indicator);
                $isClearedMissedFlag = !$apptPassed && empty($indicator);

                if ($isAttendedConfirmation || $isClearedMissedFlag) {
                    if (($metadata[$idExact]['status'] ?? '') !== 'complete') {
                        $metadata[$idExact]['status'] = 'complete';
                        $metadata[$idExact]["completedBy"] = "REDCap";
                        $changeOccurred = true;

                        $autoReason = $isAttendedConfirmation
                            ? 'Attendance indicator confirmed'
                            : 'Missed/cancelled indicator cleared';

                        $pendingLogs[] = [
                            'type' => 'auto_completed',
                            'call_id' => $idExact,
                            'call_name' => $callConfig['name'],
                            'reason' => $autoReason
                        ];
                    }
                }
            }
        }

        return $changeOccurred;
    }

    private function metadataNeedToSchedule(int $projectId, string $record, array &$metadata, array $config, string $trigger, ?string $savedInstrument, array &$pendingLogs, ?array $prefetchedData = null): bool
    {
        if (empty($config)) return false;

        global $Proj;
        $changeOccurred = false;
        $today = date('Y-m-d');

        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new Project($projectId);
        }

        $orderedEvents = array_combine(
            array_map(fn($x) => $x['day_offset'], $Proj->eventInfo ?? []),
            array_keys($Proj->eventInfo ?? [])
        );

        foreach ($config as $callConfig) {
            $data = $prefetchedData ?? (REDCap::getData($projectId, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator'], $callConfig['skip']])[$record] ?? []);
            $searchKey = array_search($callConfig['event'], $orderedEvents, true);
            $prevEvent = $searchKey !== false && isset($orderedEvents[$searchKey - 1]) ? $orderedEvents[$searchKey - 1] : null;

            if ($prevEvent && empty($metadata[$callConfig['id']]) && !empty($data[$prevEvent][$callConfig['indicator']]) && empty($data[$callConfig['event']][$callConfig['apptDate']]) && empty($data[$callConfig['event']][$callConfig['indicator']])) {
                $metadata[$callConfig['id']] = [
                    "created" => date('Y-m-d H:i:s'),
                    "template" => 'nts',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "status" => "incomplete"
                ];
                $changeOccurred = true;

                $pendingLogs[] = [
                    'type' => 'generated',
                    'call_id' => $callConfig['id'],
                    'call_name' => $callConfig['name'],
                    'template' => 'nts',
                    'trigger' => $trigger,
                    'details' => [
                        'prev_event' => $prevEvent,
                        'trigger_instrument' => $savedInstrument ?? ''
                    ]
                ];
            } elseif (!empty($metadata[$callConfig['id']]) && !empty($data[$callConfig['event']][$callConfig['apptDate']])) {
                if (($metadata[$callConfig['id']]['status'] ?? '') !== 'complete') {
                    $metadata[$callConfig['id']]['status'] = 'complete';
                    $metadata[$callConfig['id']]['completedBy'] = "REDCap";
                    $changeOccurred = true;

                    $pendingLogs[] = [
                        'type' => 'auto_completed',
                        'call_id' => $callConfig['id'],
                        'call_name' => $callConfig['name'],
                        'reason' => 'Next milestone appointment date entered (' . $data[$callConfig['event']][$callConfig['apptDate']] . ')'
                    ];
                }
            }
        }

        return $changeOccurred;
    }

    private function metadataPhoneVisit(int $projectId, string $record, array &$metadata, array $config, string $trigger, ?string $savedInstrument, array &$pendingLogs, ?array $prefetchedData = null): bool
    {
        $changeOccurred = false;
        foreach ($config as $callConfig) {
            $data = $prefetchedData ?? (REDCap::getData($projectId, 'array', $record, $callConfig['indicator'])[$record] ?? []);
            if (!empty($metadata[$callConfig['id']]) || empty($data[$callConfig['event']][$callConfig['indicator']])) continue;
            $endDate = $data[$callConfig['event']][$callConfig['autoRemove']] ?? '';
            $metadata[$callConfig['id']] = [
                "template" => 'visit',
                "event_id" => $callConfig['event'],
                "created" => date('Y-m-d H:i:s'),
                "end" => $endDate,
                "name" => $callConfig['name'],
                "instances" => [],
                "voiceMails" => 0,
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                "status" => "incomplete"
            ];
            $changeOccurred = true;

            $pendingLogs[] = [
                'type' => 'generated',
                'call_id' => $callConfig['id'],
                'call_name' => $callConfig['name'],
                'template' => 'visit',
                'trigger' => $trigger,
                'details' => [
                    'end_date' => $endDate,
                    'trigger_instrument' => $savedInstrument ?? ''
                ]
            ];
        }

        return $changeOccurred;
    }

    private function dispatchLog(int $projectId, string $record, array $item): void
    {
        if (!$this->loggingService) return;

        switch ($item['type'] ?? '') {
            case 'generated':
                $this->loggingService->logCallGenerated(
                    $projectId,
                    $record,
                    $item['call_id'],
                    $item['call_name'],
                    $item['template'],
                    $item['trigger'],
                    $item['details'] ?? []
                );
                break;
            case 'updated':
                $this->loggingService->logCallUpdated(
                    $projectId,
                    $record,
                    $item['call_id'],
                    $item['call_name'],
                    $item['details'] ?? []
                );
                break;
            case 'auto_completed':
                $this->loggingService->logCallAutoCompleted(
                    $projectId,
                    $record,
                    $item['call_id'],
                    $item['call_name'],
                    $item['reason'] ?? 'REDCap automation'
                );
                break;
            case 'removed':
                $this->loggingService->logCallRemoved(
                    $projectId,
                    $record,
                    $item['call_id'],
                    $item['call_name'],
                    $item['reason'] ?? 'Trigger condition cleared'
                );
                break;
        }
    }
}
