<?php

namespace UWMadison\CallLog\Services;

use REDCap;
use RestUtility;
use UWMadison\CallLog\CallMetadataRepository;
use UWMadison\CallLog\CallTemplateType;
use UWMadison\CallLog\CallItemDTO;

class ApiService
{
    private $module;
    private ConfigService $configService;
    private CallGeneratorService $generatorService;
    private $metadataRepo;

    public function __construct($module, ConfigService $configService, CallGeneratorService $generatorService, $metadataRepo)
    {
        $this->module = $module;
        $this->configService = $configService;
        $this->generatorService = $generatorService;
        $this->metadataRepo = $metadataRepo;
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
                $result['message'] = "Created {$createdCount} new ad-hoc call(s).";
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
                        }
                    }
                    unset($callData);
                    if ($modified) {
                        $this->metadataRepo->saveMetadata($projectId, $rec, $metadata);
                    }
                }
                $success = true;
                $result['resolvedCount'] = $resolvedCount;
                $result['message'] = "Resolved {$resolvedCount} matching ad-hoc call(s).";
                break;

            case "generate":
            case "generateCalls":
            case "newEntry":
            case "schedule":
            case "newEntryLoad":
            case "scheduleLoad":
                if ($projectId > 0) {
                    $count = $this->generatorService->evaluateAndGenerateForProject($projectId);
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
        $date = !empty($payload['date']) ? $payload['date'] : date('Y-m-d');
        $time = !empty($payload['time']) ? $payload['time'] : '00:00';
        $reported = date('Y-m-d H:i:s');
        $reporter = $payload['reporter'] ?? 'API User';

        $key = $targetConfig['id'] . '||' . $reported;
        $reasonText = $targetConfig['reasons'][$reasonId] ?? $reasonId;

        $metadata[$key] = [
            "id" => $targetConfig['id'],
            "start" => $date,
            "contactOn" => trim("{$date} {$time}"),
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
            "complete" => false
        ];

        return $this->metadataRepo->saveMetadata($projectId, $record, $metadata);
    }
}
