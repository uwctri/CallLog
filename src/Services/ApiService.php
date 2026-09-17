<?php

namespace UWMadison\CallLog\Services;

use UWMadison\CallLog\CallMetadataRepository;

class ApiService
{
    private $module;
    private ConfigService $configService;
    private CallGeneratorService $generatorService;
    private CallMetadataRepository $metadataRepo;
    private ?LoggingService $loggingService;
    private ?DateMathService $dateMathService = null;

    public function __construct(
        $module,
        ConfigService $configService,
        CallGeneratorService $generatorService,
        CallMetadataRepository $metadataRepo,
        ?LoggingService $loggingService = null
    ) {
        $this->module = $module;
        $this->configService = $configService;
        $this->generatorService = $generatorService;
        $this->metadataRepo = $metadataRepo;
        $this->loggingService = $loggingService;
    }

    private function getDateMathService(): DateMathService
    {
        return $this->dateMathService ??= new DateMathService();
    }

    public function parseTimeTo24(?string $time): string
    {
        return $this->getDateMathService()->parseTimeTo24($time);
    }

    /**
     * Handle External Module API requests (action: newAdhoc, resolveAdhoc, generate, etc.)
     */
    public function handleApiRequest(int $projectId, array $payload): array
    {
        $action = $payload['action'] ?? '';
        $records = json_decode($payload['record_list'] ?? '', true);
        if (!is_array($records)) $records = !empty($payload['record']) ? [$payload['record']] : [];

        $success = true;
        $result = [];

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
                    if ($this->createAdhocCall($projectId, $rec, (string)$callTypeId, (string)$reasonId, $payload, 'api')) {
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

    /**
     * Handle REDCap AJAX requests routed from CallLog::redcap_module_ajax
     */
    public function handleAjaxRequest(string $action, array $payload, int $projectId, string $record): array
    {
        $record = !empty($payload['record']) ? (string)$payload['record'] : $record;
        $success = true;
        $result = [];
        $callListData = false;

        $metadataActions = ['metadataSave', 'setCallStarted', 'setCallEnded', 'setNoCallsToday', 'newAdhoc'];
        $metadata = (!empty($record) && in_array($action, $metadataActions, true))
            ? $this->metadataRepo->getMetadata($projectId, $record)
            : [];

        switch ($action) {
            case "getData":
                $queryService = $this->module->getQueryService();
                $includeCompleted = !empty($payload['includeCompleted']);
                $includeExpired = !empty($payload['includeExpired']);
                $clientVersion = $payload['clientVersion'] ?? null;
                $force = !empty($payload['force']);

                $serverVersion = $queryService->getCallDataVersion($projectId, $includeCompleted, $includeExpired);
                $result['serverVersion'] = $serverVersion;

                if (!$force && !empty($clientVersion) && $clientVersion === $serverVersion) {
                    $result['changed'] = false;
                    $callListData = null;
                } else {
                    $callListRes = $queryService->getCallListData($projectId, [
                        'includeCompleted' => $includeCompleted,
                        'includeExpired' => $includeExpired
                    ]);
                    $result['changed'] = true;
                    $result['showCallback'] = $callListRes['showCallback'] ?? false;
                    $callListData = $callListRes['data'] ?? [];
                }
                break;

            case "deployInstruments":
                $eventId = isset($payload['event_id']) && $payload['event_id'] !== '' ? (int)$payload['event_id'] : null;
                $deployRes = $this->module->getDeploymentService()->deploy($projectId, dirname(__DIR__, 2) . '/call.csv', $eventId);
                $success = $deployRes['success'];
                $result = $deployRes;
                break;

            case "resetCallLogInstrument":
                $resetRes = $this->module->getDeploymentService()->resetCallLogInstrument($projectId, dirname(__DIR__, 2) . '/call.csv');
                $success = $resetRes['success'];
                $result = $resetRes;
                if ($success && $this->loggingService) {
                    $user = defined('USERID') ? USERID : '';
                    $this->loggingService->logMetadataAction($projectId, 'ALL', 'RESET_INSTRUMENT', $user, [
                        'message' => 'Reset Call Log instrument fields to native call.csv defaults'
                    ]);
                }
                break;

            case "enableRepeatable":
                $eventId = isset($payload['event_id']) && $payload['event_id'] !== '' ? (int)$payload['event_id'] : null;
                $repRes = $this->module->getDeploymentService()->enableRepeatable($projectId, $eventId);
                $success = $repRes['success'];
                $result = $repRes;
                break;

            case "saveConfig":
                if (!empty($payload['settings']) && is_array($payload['settings'])) {
                    $saved = $this->configService->saveProjectSettings($projectId, $payload['settings']);
                    $result['saved'] = $saved;
                    if ($saved && $this->loggingService) {
                        $user = defined('USERID') ? USERID : '';
                        $callTypesCount = count($payload['settings']['call_id'] ?? []);
                        $tabsCount = count($payload['settings']['tab_id'] ?? []);
                        $this->loggingService->logConfigSaved($projectId, $user, [
                            'call_types_count' => $callTypesCount,
                            'tabs_count' => $tabsCount
                        ]);
                    }
                }
                break;

            case "newAdhoc":
                if (!empty($payload['id'])) {
                    $callTypeId = (string)$payload['id'];
                    $reasonId = (string)($payload['reason'] ?? '');
                    $result['saved'] = $this->createAdhocCall($projectId, $record, $callTypeId, $reasonId, $payload, 'ui');
                }
                break;

            case "callDelete":
                $this->metadataRepo->deleteLastCallInstance($projectId, $record);
                $user = defined('USERID') ? USERID : '';
                if ($this->loggingService) {
                    $this->loggingService->logCallInstanceDeleted($projectId, $record, $user);
                }
                break;

            case "metadataSave":
                if (!empty($payload['metadata'])) {
                    $savedData = json_decode($payload['metadata'], true);
                    if (is_array($savedData)) {
                        $result['saved'] = $this->metadataRepo->saveMetadata($projectId, $record, $savedData);
                    }
                }
                break;

            case "setCallStarted":
                $user = !empty($payload['user']) ? $payload['user'] : (defined('USERID') ? USERID : '');
                $callId = !empty($payload['id']) ? (string)$payload['id'] : '';
                if (empty($record)) {
                    $result['saved'] = false;
                    $result['error'] = 'Record ID is missing.';
                    break;
                }
                if (empty($callId)) {
                    $result['saved'] = false;
                    $result['error'] = 'Call ID is missing.';
                    break;
                }
                if (empty($metadata)) {
                    $metadata = $this->metadataRepo->getMetadata($projectId, $record);
                }
                $targetKey = isset($metadata[$callId]) ? $callId : null;
                if (!$targetKey) {
                    foreach ($metadata as $k => $v) {
                        if ($k === $callId || ($v['id'] ?? '') === $callId || strpos($k, $callId . '|') === 0 || strpos($callId, $k . '|') === 0) {
                            $targetKey = $k;
                            break;
                        }
                    }
                }
                if (!$targetKey) {
                    $targetKey = $callId;
                }
                if (!isset($metadata[$targetKey]) || !is_array($metadata[$targetKey])) {
                    $metadata[$targetKey] = [];
                }
                if (empty($metadata[$targetKey]['name'])) {
                    $rawSettings = $this->configService->getRawProjectSettings($projectId);
                    $baseId = explode('|', explode('||', (string)$targetKey)[0])[0];
                    foreach ($rawSettings['call_id'] ?? [] as $i => $cid) {
                        if ($cid === $baseId || $cid === $targetKey) {
                            $metadata[$targetKey]['name'] = $rawSettings['call_name'][$i] ?? $targetKey;
                            $metadata[$targetKey]['template'] = $rawSettings['call_template'][$i] ?? 'new';
                            $metadata[$targetKey]['id'] = $targetKey;
                            $metadata[$targetKey]['status'] = 'incomplete';
                            break;
                        }
                    }
                }
                $startTime = date("Y-m-d H:i:s");
                $metadata[$targetKey]['callStarted'] = $startTime;
                $metadata[$targetKey]['callStartedBy'] = $user;
                $saved = $this->metadataRepo->saveMetadata($projectId, $record, $metadata);
                $result['saved'] = $saved;
                $result['callStarted'] = $startTime;
                $result['callStartedBy'] = $user;
                if ($saved && $this->loggingService) {
                    $this->loggingService->logCallStarted($projectId, $record, (string)$targetKey, $user);
                } else if (!$saved) {
                    $result['error'] = 'Failed to save call start state to metadata.';
                }
                break;

            case "setCallEnded":
                $user = defined('USERID') ? USERID : '';
                $callId = !empty($payload['id']) ? (string)$payload['id'] : '';
                if (empty($record)) {
                    $result['saved'] = false;
                    $result['error'] = 'Record ID is missing.';
                    break;
                }
                if (empty($callId)) {
                    $result['saved'] = false;
                    $result['error'] = 'Call ID is missing.';
                    break;
                }
                if (empty($metadata)) {
                    $metadata = $this->metadataRepo->getMetadata($projectId, $record);
                }
                $targetKey = isset($metadata[$callId]) ? $callId : null;
                if (!$targetKey) {
                    foreach ($metadata as $k => $v) {
                        if ($k === $callId || ($v['id'] ?? '') === $callId || strpos($k, $callId . '|') === 0 || strpos($callId, $k . '|') === 0) {
                            $targetKey = $k;
                            break;
                        }
                    }
                }
                if (!$targetKey) {
                    $targetKey = $callId;
                }
                if (!isset($metadata[$targetKey]) || !is_array($metadata[$targetKey])) {
                    $metadata[$targetKey] = [];
                }
                $metadata[$targetKey]['callStarted'] = '';
                $metadata[$targetKey]['callStartedBy'] = '';
                $saved = $this->metadataRepo->saveMetadata($projectId, $record, $metadata);
                $result['saved'] = $saved;
                if ($saved && $this->loggingService) {
                    $this->loggingService->logCallEnded($projectId, $record, (string)$targetKey, $user);
                } else if (!$saved) {
                    $result['error'] = 'Failed to clear call start state in metadata.';
                }
                break;

            case "setNoCallsToday":
                $user = defined('USERID') ? USERID : '';
                $callId = !empty($payload['id']) ? (string)$payload['id'] : '';
                if (empty($record)) {
                    $result['saved'] = false;
                    $result['error'] = 'Record ID is missing.';
                    break;
                }
                if (empty($callId)) {
                    $result['saved'] = false;
                    $result['error'] = 'Call ID is missing.';
                    break;
                }
                if (empty($metadata)) {
                    $metadata = $this->metadataRepo->getMetadata($projectId, $record);
                }
                $targetKey = isset($metadata[$callId]) ? $callId : null;
                if (!$targetKey) {
                    foreach ($metadata as $k => $v) {
                        if ($k === $callId || ($v['id'] ?? '') === $callId || strpos($k, $callId . '|') === 0 || strpos($callId, $k . '|') === 0) {
                            $targetKey = $k;
                            break;
                        }
                    }
                }
                if (!$targetKey) {
                    $targetKey = $callId;
                }
                if (!isset($metadata[$targetKey]) || !is_array($metadata[$targetKey])) {
                    $metadata[$targetKey] = [];
                }
                if (!is_array($metadata[$targetKey]['noCallsToday'] ?? null)) {
                    $metadata[$targetKey]['noCallsToday'] = [];
                }
                $todayDate = date('Y-m-d');
                $metadata[$targetKey]['noCallsToday'][] = $todayDate;
                $saved = $this->metadataRepo->saveMetadata($projectId, $record, $metadata);
                $result['saved'] = $saved;
                $result['date'] = $todayDate;
                if ($saved && $this->loggingService) {
                    $this->loggingService->logNoCallsToday($projectId, $record, (string)$targetKey, $user, $todayDate);
                } else if (!$saved) {
                    $result['error'] = 'Failed to save no calls today state to metadata.';
                }
                break;

            case "generate":
            case "generateCalls":
            case "newEntryLoad":
            case "scheduleLoad":
                if ($projectId > 0) {
                    $count = $this->generatorService->evaluateAndGenerateForProject($projectId, 'manual_dashboard');
                    $success = true;
                    $result['generatedCount'] = $count;
                    $result['message'] = "Call log generation completed for project {$projectId}.";
                } else {
                    $success = false;
                    $result['error'] = "Missing or invalid project ID.";
                }
                break;

            case "saveUserColumns":
                $tabId = $payload['tab_id'] ?? '';
                $order = $payload['order'] ?? [];
                $hidden = $payload['hidden'] ?? [];
                if (!empty($tabId) && method_exists($this->module, 'getUserSetting')) {
                    $raw = $this->module->getUserSetting('user-settings');
                    $userSettings = (!empty($raw) && is_string($raw))
                        ? json_decode($raw, true)
                        : (is_array($raw) ? $raw : []);
                    if (!is_array($userSettings)) {
                        $userSettings = [];
                    }
                    if (!isset($userSettings['tabs'])) {
                        $userSettings['tabs'] = [];
                    }
                    $userSettings['tabs'][$tabId] = [
                        'order' => is_array($order) ? array_values($order) : [],
                        'hidden' => is_array($hidden) ? array_values($hidden) : [],
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    $this->module->setUserSetting('user-settings', json_encode($userSettings));
                    $result['saved'] = true;
                }
                break;

            case "resetUserColumns":
                $tabId = $payload['tab_id'] ?? '';
                if (!empty($tabId) && method_exists($this->module, 'getUserSetting')) {
                    $raw = $this->module->getUserSetting('user-settings');
                    $userSettings = (!empty($raw) && is_string($raw))
                        ? json_decode($raw, true)
                        : (is_array($raw) ? $raw : []);
                    if (is_array($userSettings) && isset($userSettings['tabs'][$tabId])) {
                        unset($userSettings['tabs'][$tabId]);
                        $this->module->setUserSetting('user-settings', json_encode($userSettings));
                    }
                    $result['saved'] = true;
                }
                break;

            case "getReportsData":
                $timeframe = $payload['timeframe'] ?? '30d';
                $result['reports'] = $this->module->getReportService()->getReportsData($projectId, $timeframe);
                $success = true;
                break;
        }

        return array_merge([
            "action" => $action,
            "success" => $success,
            "data" => $callListData
        ], $result);
    }

    /**
     * Unified creation of adhoc call instances (used by both API and UI AJAX requests)
     */
    public function createAdhocCall(int $projectId, string $record, string $callTypeId, string $reasonId, array $payload, string $source = 'ui'): bool
    {
        $config = $this->configService->getAdhocTemplateConfig($projectId);
        $targetConfig = $config[$callTypeId] ?? null;

        // Fallback: search array if not keyed by callTypeId directly
        if (!$targetConfig) {
            foreach ($config as $cfg) {
                if (($cfg['id'] ?? '') === $callTypeId) {
                    $targetConfig = $cfg;
                    break;
                }
            }
        }
        if (!$targetConfig) return false;

        $metadata = $this->metadataRepo->getMetadata($projectId, $record);
        $genDate = !empty($payload['generationDate']) ? $payload['generationDate'] : (!empty($payload['date']) ? $payload['date'] : date('Y-m-d'));
        $rawGenTime = !empty($payload['generationTime']) ? $payload['generationTime'] : (!empty($payload['time']) ? $payload['time'] : date('H:i'));
        $genTime = $this->parseTimeTo24($rawGenTime);
        $reportedTs = strtotime("{$genDate} {$genTime}");
        $reported = ($reportedTs !== false) ? date('Y-m-d H:i:s', $reportedTs) : date('Y-m-d H:i:s');

        $userMap = method_exists($this->module, 'getUserNameMap') ? $this->module->getUserNameMap($projectId) : [];
        $rawReporter = $payload['reporter'] ?? ($source === 'api' ? 'API User' : '');
        $reporter = $userMap[$rawReporter] ?? $rawReporter;

        $hasCallback = !empty($payload['schedule_callback']) || !empty($payload['scheduleCallback']) || !empty($payload['callback_date']) || !empty($payload['callbackDate']);
        $cbDate = $payload['callback_date'] ?? $payload['callbackDate'] ?? null;
        if (!empty($cbDate) && strpos($cbDate, '/') !== false) {
            $ts = strtotime($cbDate);
            if ($ts !== false) $cbDate = date('Y-m-d', $ts);
        }
        $cbTime = ($hasCallback && !empty($payload['callback_time'] ?? $payload['callbackTime']))
            ? $this->parseTimeTo24($payload['callback_time'] ?? $payload['callbackTime'])
            : '09:00';
        $cbRequestor = !empty($payload['callback_requestor'] ?? $payload['callbackRequestor'])
            ? (string)($payload['callback_requestor'] ?? $payload['callbackRequestor'])
            : '1';

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
            "callbackDate" => ($hasCallback && $cbDate) ? $cbDate : null,
            "callbackTime" => ($hasCallback && $cbDate) ? $cbTime : null,
            "callbackRequestor" => ($hasCallback && $cbDate) ? $cbRequestor : null,
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
                $source,
                $logExtra
            );
        }

        return $saved;
    }
}
