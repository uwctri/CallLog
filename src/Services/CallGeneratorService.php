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

    public function __construct($module, ConfigService $configService, $metadataRepo, DateMathService $dateMathService)
    {
        $this->module = $module;
        $this->configService = $configService;
        $this->metadataRepo = $metadataRepo;
        $this->dateMathService = $dateMathService;
    }

    public function evaluateAndGenerateForRecord(int $projectId, string $record, ?string $savedInstrument = null): bool
    {
        $metadata = $this->metadataRepo->getMetadata($projectId, $record);
        $config = $this->configService->getCallTemplateConfig($projectId);
        $triggerForms = $this->configService->getRawProjectSettings($projectId)['trigger_save'];

        $changes = [];
        if (empty($triggerForms) || ($savedInstrument && in_array($savedInstrument, $triggerForms, true))) {
            $changes[] = $this->metadataFollowup($projectId, $record, $metadata, $config['followup']);
            $changes[] = $this->metadataReminder($projectId, $record, $metadata, $config['reminder']);
            $changes[] = $this->metadataMissedCancelled($projectId, $record, $metadata, $config['mcv']);
            $changes[] = $this->metadataNeedToSchedule($projectId, $record, $metadata, $config['nts']);
        }

        $changes[] = $this->metadataNewEntry($projectId, $record, $metadata, $config['new']);
        $changes[] = $this->metadataPhoneVisit($projectId, $record, $metadata, $config['visit']);

        if (in_array(true, $changes, true)) {
            return $this->metadataRepo->saveMetadata($projectId, $record, $metadata);
        }

        return false;
    }

    public function evaluateAndGenerateForProject(int $projectId): int
    {
        $recordIdField = REDCap::getRecordIdField();
        $records = REDCap::getData($projectId, 'json', null, [$recordIdField]);
        $recordsArray = json_decode($records, true);
        if (empty($recordsArray)) return 0;

        $recordIds = array_unique(array_column($recordsArray, $recordIdField));
        $generatedCount = 0;

        foreach ($recordIds as $record) {
            if ($this->evaluateAndGenerateForRecord($projectId, (string)$record)) {
                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    private function metadataNewEntry(int $projectId, string $record, array &$metadata, array $config): bool
    {
        if (!empty($metadata)) return false;
        $changeOccurred = false;

        foreach ($config as $callConfig) {
            if (!empty($metadata[$callConfig['id']])) continue;
            $dto = new CallItemDTO([
                "id" => $callConfig['id'],
                "template" => CallTemplateType::NEW->value,
                "event_id" => '',
                "name" => $callConfig['name'],
                "load" => date("Y-m-d H:i"),
                "instances" => [],
                "voiceMails" => 0,
                "expire" => $callConfig['expire'],
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                "complete" => false
            ]);
            $metadata[$callConfig['id']] = $dto->toArray();
            $changeOccurred = true;
        }

        return $changeOccurred;
    }

    private function metadataFollowup(int $projectId, string $record, array &$metadata, array $config): bool
    {
        $changeOccurred = false;
        foreach ($config as $callConfig) {
            $data = REDCap::getData($projectId, 'array', $record, [$callConfig['field'], $callConfig['end']])[$record] ?? [];
            $fieldVal = $data[$callConfig['event']][$callConfig['field']] ?? '';

            if (!empty($metadata[$callConfig['id']]) && empty($fieldVal)) {
                unset($metadata[$callConfig['id']]);
                $changeOccurred = true;
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
                        "template" => 'followup',
                        "event_id" => $callConfig['event'],
                        "name" => $callConfig['name'],
                        "instances" => [],
                        "voiceMails" => 0,
                        "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                        "complete" => false
                    ];
                    $changeOccurred = true;
                } else {
                    if (($metadata[$callConfig['id']]['start'] ?? '') !== $start || ($metadata[$callConfig['id']]['end'] ?? '') !== $end) {
                        $metadata[$callConfig['id']]['start'] = $start;
                        $metadata[$callConfig['id']]['end'] = $end;
                        $changeOccurred = true;
                    }
                }
            }
        }

        return $changeOccurred;
    }

    private function metadataReminder(int $projectId, string $record, array &$metadata, array $config): bool
    {
        $changeOccurred = false;
        $today = date('Y-m-d');

        foreach ($config as $callConfig) {
            $data = REDCap::getData($projectId, 'array', $record, [$callConfig['field'], $callConfig['removeVar']])[$record] ?? [];
            $removeFlag = $data[$callConfig['removeEvent']][$callConfig['removeVar']] ?? false;

            if (!empty($metadata[$callConfig['id']]) && empty($metadata[$callConfig['id']]['instances']) && $removeFlag) {
                unset($metadata[$callConfig['id']]);
                $changeOccurred = true;
                continue;
            }

            if ($removeFlag) continue;

            $fieldVal = $data[$callConfig['event']][$callConfig['field']] ?? '';
            $rawSettings = $this->configService->getRawProjectSettings($projectId);
            $enabledHolidays = $rawSettings['enabled_holidays'] ?? null;
            $customDates = $rawSettings['custom_holidays_date'] ?? null;
            $customNames = $rawSettings['custom_holidays_name'] ?? null;

            $newStart = $this->dateMathService->dateMath($fieldVal, '-', $callConfig['days'], $enabledHolidays, $customDates, $customNames);
            $newEnd = $this->dateMathService->dateMath($fieldVal, '+', $callConfig['days'] == 0 ? 365 : 0, $enabledHolidays, $customDates, $customNames);

            if (!empty($metadata[$callConfig['id']]) && empty($fieldVal) && empty($metadata[$callConfig['id']]['instances'])) {
                unset($metadata[$callConfig['id']]);
                $changeOccurred = true;
            } elseif (!empty($metadata[$callConfig['id']]) && empty($fieldVal)) {
                $metadata[$callConfig['id']]["complete"] = true;
                $metadata[$callConfig['id']]["completedBy"] = "REDCap";
                $changeOccurred = true;
            } elseif (!empty($metadata[$callConfig['id']]) && !empty($fieldVal) && ($fieldVal <= $today)) {
                $metadata[$callConfig['id']]['complete'] = true;
                $metadata[$callConfig['id']]["completedBy"] = "REDCap";
                $changeOccurred = true;
            } elseif (!empty($metadata[$callConfig['id']]) && !empty($fieldVal) && (($metadata[$callConfig['id']]['start'] ?? '') !== $newStart || ($metadata[$callConfig['id']]['end'] ?? '') !== $newEnd)) {
                $metadata[$callConfig['id']]['complete'] = false;
                $metadata[$callConfig['id']]['start'] = $newStart;
                $metadata[$callConfig['id']]['end'] = $newEnd;
                $changeOccurred = true;
            } elseif (empty($metadata[$callConfig['id']]) && !empty($fieldVal)) {
                $metadata[$callConfig['id']] = [
                    "start" => $newStart,
                    "end" => $newEnd,
                    "template" => 'reminder',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
                $changeOccurred = true;
            }
        }

        return $changeOccurred;
    }

    private function metadataMissedCancelled(int $projectId, string $record, array &$metadata, array $config): bool
    {
        $changeOccurred = false;
        foreach ($config as $callConfig) {
            $data = REDCap::getData($projectId, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator']])[$record][$callConfig['event']] ?? [];
            $apptDate = $data[$callConfig['apptDate']] ?? '';
            $indicator = $data[$callConfig['indicator']] ?? '';
            $idExact = $callConfig['id'] . '||' . $apptDate;

            if (empty($metadata[$idExact]) && !empty($apptDate) && !empty($indicator)) {
                $metadata[$idExact] = [
                    "appt" => $apptDate,
                    "template" => 'mcv',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
                $changeOccurred = true;
            } elseif (!empty($metadata[$idExact]) && !empty($apptDate) && empty($indicator)) {
                $metadata[$idExact]['complete'] = true;
                $metadata[$idExact]["completedBy"] = "REDCap";
                $changeOccurred = true;
            }
        }

        return $changeOccurred;
    }

    private function metadataNeedToSchedule(int $projectId, string $record, array &$metadata, array $config): bool
    {
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
            $data = REDCap::getData($projectId, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator'], $callConfig['skip']])[$record] ?? [];
            $searchKey = array_search($callConfig['event'], $orderedEvents, true);
            $prevEvent = $searchKey !== false && isset($orderedEvents[$searchKey - 1]) ? $orderedEvents[$searchKey - 1] : null;

            if ($prevEvent && empty($metadata[$callConfig['id']]) && !empty($data[$prevEvent][$callConfig['indicator']]) && empty($data[$callConfig['event']][$callConfig['apptDate']]) && empty($data[$callConfig['event']][$callConfig['indicator']])) {
                $metadata[$callConfig['id']] = [
                    "created" => $today,
                    "template" => 'nts',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
                $changeOccurred = true;
            } elseif (!empty($metadata[$callConfig['id']]) && !empty($data[$callConfig['event']][$callConfig['apptDate']])) {
                $metadata[$callConfig['id']]['complete'] = true;
                $metadata[$callConfig['id']]['completedBy'] = "REDCap";
                $changeOccurred = true;
            }
        }

        return $changeOccurred;
    }

    private function metadataPhoneVisit(int $projectId, string $record, array &$metadata, array $config): bool
    {
        $changeOccurred = false;
        foreach ($config as $callConfig) {
            $data = REDCap::getData($projectId, 'array', $record, $callConfig['indicator'])[$record] ?? [];
            if (!empty($metadata[$callConfig['id']]) || empty($data[$callConfig['event']][$callConfig['indicator']])) continue;
            $metadata[$callConfig['id']] = [
                "template" => 'visit',
                "event_id" => $callConfig['event'],
                "end" => $data[$callConfig['event']][$callConfig['autoRemove']] ?? '',
                "name" => $callConfig['name'],
                "instances" => [],
                "voiceMails" => 0,
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                "complete" => false
            ];
            $changeOccurred = true;
        }

        return $changeOccurred;
    }
}
