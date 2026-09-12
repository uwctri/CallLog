<?php

namespace UWMadison\CallLog\Services;

use REDCap;
use RestUtility;
use UWMadison\CallLog\CallMetadataRepository;
use UWMadison\CallTemplateType;
use UWMadison\CallLog\CallItemDTO;

class ApiService
{
    private $module;
    private ConfigService $configService;
    private CallGeneratorService $generatorService;
    private $metadataRepo;
    private ?LoggingService $loggingService;

    public function __construct(
        $module,
        ConfigService $configService,
        CallGeneratorService $generatorService,
        $metadataRepo,
        ?LoggingService $loggingService = null
    ) {
        $this->module = $module;
        $this->configService = $configService;
        $this->generatorService = $generatorService;
        $this->metadataRepo = $metadataRepo;
        $this->loggingService = $loggingService;
    }

    public function handleApiRequest(int $projectId, array $payload): array
    {
        $action = $payload['action'] ?? '';
        $records = json_decode($payload['record_list'] ?? '', true);
        if (!is_array($records)) {
            $records = !empty($payload['record']) ? [$payload['record']] : [];
        }

        $success = true;
        $result = [];
        $config = $this->configService->getCallTemplateConfig($projectId);

        switch ($action) {
            case "newAdhoc":
                $callTypeId = $payload['call_type_id'] ?? $payload['type'] ?? $payload['call_id'] ?? '';
                $reasonId = $payload['reason_id'] ?? $payload['reason'] ?? $payload['code'] ?? '';

                if (empty($records)) {
                    $success = false;
                    $result['error'] = "Missing required 'record' or 'record_list' parameter for newAdhoc.";
                    break;
                }
                if (empty($callTypeId)) {
                    $success = false;
                    $result['error'] = "Missing required 'call_type_id' parameter for newAdhoc.";
                    break;
                }
                if (empty($reasonId)) {
                    $success = false;
                    $result['error'] = "Missing required 'reason_id' parameter for newAdhoc.";
                    break;
                }

                $createdCount = 0;
                foreach ($records as $record) {
                    $rec = trim((string)$record);
                    if (empty($rec)) continue;
                    if ($this->createAdhocCall($projectId, $rec, $config['adhoc'] ?? [], $callTypeId, $reasonId, $payload)) {
                        $createdCount++;
                    }
                }
                $success = true;
                $result['createdCount'] = $createdCount;
                $result['message'] = "Created {$createdCount} new adhoc call(s).";
                break;

            case "resolveAdhoc":
                $callTypeId = $payload['call_type_id'] ?? $payload['type'] ?? $payload['call_id'] ?? '';
                $reasonId = $payload['reason_id'] ?? $payload['reason'] ?? $payload['code'] ?? '';

                if (empty($records)) {
                    $success = false;
                    $result['error'] = "Missing required 'record' or 'record_list' parameter for resolveAdhoc.";
                    break;
                }
                if (empty($callTypeId)) {
                    $success = false;
                    $result['error'] = "Missing required 'call_type_id' parameter for resolveAdhoc.";
                    break;
                }
                if (empty($reasonId)) {
                    $success = false;
                    $result['error'] = "Missing required 'reason_id' parameter for resolveAdhoc.";
                    break;
                }

                $resolvedCount = 0;
                foreach ($records as $record) {
                    $rec = trim((string)$record);
                    if (empty($rec)) continue;
                    $metadata = $this->metadataRepo->getMetadata($projectId, $rec);
                    $modified = false;
                    $reasonsResolved = [];
                    foreach ($metadata as $callKey => &$callData) {
                        if (!empty($callData['complete'])) continue;
                        if (($callData['template'] ?? '') !== 'adhoc') continue;

                        $matchesCallType = ($callData['id'] ?? '') === $callTypeId || strpos($callKey, $callTypeId . '||') === 0 || strpos($callKey, $callTypeId) === 0;
                        $matchesReason = (string)($callData['reason'] ?? '') === (string)$reasonId;

                        if ($matchesCallType && $matchesReason) {
                            $callData['complete'] = true;
                            $callData['completedBy'] = "REDCap API";
                            $modified = true;
                            $resolvedCount++;
                            $reasonsResolved[] = (string)$reasonId;
                        }
                    }
                    unset($callData);
                    if ($modified) {
                        $this->metadataRepo->saveMetadata($projectId, $rec, $metadata);
                        if ($this->loggingService) {
                            $this->loggingService->logAdhocResolved($projectId, $rec, $callTypeId, (string)$reasonId, count($reasonsResolved));
                        }
                    }
                }
                $success = true;
                $result['resolvedCount'] = $resolvedCount;
                $result['message'] = "Resolved {$resolvedCount} matching adhoc call(s).";
                break;

            case "generate":
            case "generateCalls":
            case "newEntry":
            case "schedule":
            case "newEntryLoad":
            case "scheduleLoad":
                if ($projectId > 0) {
                    $count = $this->generatorService->evaluateAndGenerateForProject($projectId, 'api');
                    $success = true;
                    $result['generatedCount'] = $count;
                    $result['message'] = "Evaluated and generated calls for project {$projectId}.";
                } else {
                    $success = false;
                    $result['error'] = "Missing or invalid project ID.";
                }
                break;

            default:
                $success = false;
                $result['error'] = "Invalid or unsupported action '{$action}'. Supported actions: newAdhoc, resolveAdhoc, generate.";
                break;
        }

        return [
            "action" => $action,
            "success" => $success,
            "result" => $result
        ];
    }

    public function parseTimeTo24(?string $time): string
    {
        if (empty($time)) return '00:00';
        $t = trim(strtolower($time));
        $isPm = false;
        $isAm = false;
        if (preg_match('/p\.?m?\.?$/i', $t)) {
            $isPm = true;
            $t = trim(preg_replace('/p\.?m?\.?$/i', '', $t));
        } elseif (preg_match('/a\.?m?\.?$/i', $t)) {
            $isAm = true;
            $t = trim(preg_replace('/a\.?m?\.?$/i', '', $t));
        }
        if (strpos($t, ':') !== false || strpos($t, '.') !== false) {
            $parts = preg_split('/[:.]/', $t);
            $hours = (int)$parts[0];
            $minutes = isset($parts[1]) ? (int)$parts[1] : 0;
        } elseif (ctype_digit($t)) {
            $len = strlen($t);
            if ($len === 1 || $len === 2) {
                $hours = (int)$t;
                $minutes = 0;
            } elseif ($len === 3) {
                $hours = (int)substr($t, 0, 1);
                $minutes = (int)substr($t, 1);
            } elseif ($len === 4) {
                $hours = (int)substr($t, 0, 2);
                $minutes = (int)substr($t, 2);
            } else {
                return '00:00';
            }
        } else {
            return '00:00';
        }
        if ($minutes < 0 || $minutes > 59) return '00:00';
        if ($isPm && $hours < 12) $hours += 12;
        if ($isAm && $hours === 12) $hours = 0;
        if ($hours < 0 || $hours > 23) return '00:00';
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    private function createAdhocCall(int $projectId, string $record, array $adhocConfigList, string $callTypeId, string $reasonId, array $payload): bool
    {
        $targetConfig = null;
        foreach ($adhocConfigList as $cfg) {
            if (($cfg['id'] ?? '') === $callTypeId) {
                $targetConfig = $cfg;
                break;
            }
        }
        if (!$targetConfig) return false;

        $metadata = $this->metadataRepo->getMetadata($projectId, $record);
        $genDate = !empty($payload['generationDate']) ? $payload['generationDate'] : (!empty($payload['date']) ? $payload['date'] : date('Y-m-d'));
        $rawGenTime = !empty($payload['generationTime']) ? $payload['generationTime'] : (!empty($payload['time']) ? $payload['time'] : date('H:i'));
        $genTime = $this->parseTimeTo24($rawGenTime);
        $reportedTs = strtotime("{$genDate} {$genTime}");
        $reported = ($reportedTs !== false) ? date('Y-m-d H:i:s', $reportedTs) : date('Y-m-d H:i:s');
        $reporter = $payload['reporter'] ?? 'API User';

        $hasCallback = !empty($payload['schedule_callback']) || !empty($payload['scheduleCallback']) || !empty($payload['callback_date']) || !empty($payload['callbackDate']);
        $cbDate = $payload['callback_date'] ?? $payload['callbackDate'] ?? null;
        if (!empty($cbDate) && strpos($cbDate, '/') !== false) {
            $ts = strtotime($cbDate);
            if ($ts !== false) $cbDate = date('Y-m-d', $ts);
        }
        $cbTime = ($hasCallback && !empty($payload['callback_time'] ?? $payload['callbackTime'])) ? $this->parseTimeTo24($payload['callback_time'] ?? $payload['callbackTime']) : '09:00';
        $cbRequestor = $payload['callback_requestor'] ?? $payload['callbackRequestor'] ?? '1';

        $startDate = ($hasCallback && $cbDate) ? $cbDate : $genDate;
        $contactOn = ($hasCallback && $cbDate) ? trim("{$cbDate} {$cbTime}") : trim("{$genDate} {$genTime}");

        $key = $targetConfig['id'] . '||' . $reported;
        $reasonText = $targetConfig['reasons'][$reasonId] ?? $reasonId;

        $metadata[$key] = [
            "id" => $targetConfig['id'],
            "start" => $startDate,
            "contactOn" => $contactOn,
            "reported" => $reported,
            "reporter" => $reporter,
            "reason" => (string)$reasonId,
            "initNotes" => $payload['notes'] ?? '',
            "template" => 'adhoc',
            "event_id" => '',
            "event" => '',
            "name" => $targetConfig['name'] . ' - ' . $reasonText,
            "instances" => [],
            "voiceMails" => 0,
            "hideAfterAttempt" => $targetConfig['hideAfterAttempt'] ?? 9999,
            "status" => "incomplete",
            "complete" => false,
            "requestedCallback" => ($hasCallback && $cbDate) ? '1' : '0',
            "callbackDate" => $cbDate,
            "callbackTime" => $cbTime,
            "callbackRequestor" => $cbRequestor,
        ];

        $saved = $this->metadataRepo->saveMetadata($projectId, $record, $metadata);
        if ($saved && $this->loggingService) {
            $logExtra = [
                'generation_date' => $genDate,
                'generation_time' => $genTime,
                'contact_date' => $startDate,
                'contact_time' => ($hasCallback && $cbDate) ? ($cbTime ?? '00:00') : $genTime
            ];
            if ($hasCallback && $cbDate) {
                $logExtra['callback_scheduled'] = true;
                $logExtra['callback_date'] = $cbDate;
                $logExtra['callback_time'] = $cbTime;
                $logExtra['callback_requestor'] = $cbRequestor;
            }
            $this->loggingService->logAdhocCreated(
                $projectId,
                $record,
                $key,
                $targetConfig['name'] . ' - ' . $reasonText,
                (string)$reasonId,
                $reporter,
                'api',
                $logExtra
            );
        }

        return $saved;
    }
}
