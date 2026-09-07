<?php

namespace UWMadison\CallLog;

use ExternalModules\AbstractExternalModule;
use REDCap;
use UWMadison\CallLog\Services\ConfigService;
use UWMadison\CallLog\Services\DateMathService;
use UWMadison\CallLog\Services\CallGeneratorService;
use UWMadison\CallLog\Services\CallQueryService;
use UWMadison\CallLog\Services\InstrumentDeploymentService;
use UWMadison\CallLog\Services\ApiService;
use UWMadison\CallLog\CallMetadataRepository;
use UWMadison\CallLog\CallTemplateType;
use UWMadison\CallLog\CallItemDTO;

spl_autoload_register(function ($class) {
    $prefix = 'UWMadison\\CallLog\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * DEPENDENCY NOTE:
 * REDCap 17.4.0 supplies DataTables v1.13.11.
 * This module includes DataTables ColReorder v1.7.0 (js/dataTables.colReorder.min.js & css/colReorder.dataTables.min.css).
 * These are manually managed dependencies. If REDCap is upgraded to a version using DataTables 2.0+,
 * ColReorder must be manually updated to v2.x to maintain compatibility.
 */
class CallLog extends AbstractExternalModule
{
    public array $tabsConfig = [];

    private string $instrumentCall = "call_log";
    private string $instrumentMeta = "call_log_metadata";
    private string $metadataField = "call_metadata";
    private int $startedCallGrace = 30;

    public function __construct()
    {
        parent::__construct();
        $this->disableUserBasedSettingPermissions();
    }

    private ?ConfigService $configService = null;
    private ?CallMetadataRepository $metadataRepo = null;
    private ?DateMathService $dateMathService = null;
    private ?CallGeneratorService $generatorService = null;
    private ?CallQueryService $queryService = null;
    private ?InstrumentDeploymentService $deploymentService = null;
    private ?ApiService $apiService = null;
    private ?\UWMadison\CallLog\Services\LoggingService $loggingService = null;

    public function getConfigService(): ConfigService
    {
        return $this->configService ??= new ConfigService($this);
    }

    public function getLoggingService(): \UWMadison\CallLog\Services\LoggingService
    {
        return $this->loggingService ??= new \UWMadison\CallLog\Services\LoggingService($this);
    }

    public function getMetadataRepo(): CallMetadataRepository
    {
        return $this->metadataRepo ??= new CallMetadataRepository();
    }

    public function getDateMathService(): DateMathService
    {
        return $this->dateMathService ??= new DateMathService();
    }

    public function getGeneratorService(): CallGeneratorService
    {
        return $this->generatorService ??= new CallGeneratorService(
            $this,
            $this->getConfigService(),
            $this->getMetadataRepo(),
            $this->getDateMathService(),
            $this->getLoggingService()
        );
    }

    public function getQueryService(): CallQueryService
    {
        return $this->queryService ??= new CallQueryService(
            $this,
            $this->getConfigService(),
            $this->getMetadataRepo()
        );
    }

    public function getDeploymentService(): InstrumentDeploymentService
    {
        return $this->deploymentService ??= new InstrumentDeploymentService();
    }

    public function getApiService(): ApiService
    {
        return $this->apiService ??= new ApiService(
            $this,
            $this->getConfigService(),
            $this->getGeneratorService(),
            $this->getMetadataRepo(),
            $this->getLoggingService()
        );
    }

    public function redcap_save_record($project_id, $record, $instrument)
    {
        // Skip call generation evaluation when saving internal module metadata
        if ($instrument === $this->instrumentMeta) {
            return;
        }

        $project_id = (int)$project_id;
        $record = (string)$record;

        $this->getGeneratorService()->evaluateAndGenerateForRecord($project_id, $record, $instrument, 'save_record');
    }

    public function redcap_every_page_top($project_id)
    {
        if (!defined("USERID") || empty($project_id)) return;

        try {
            $this->initGlobal((int)$project_id);
        } catch (\Throwable $e) {
            return;
        }

        $cfg = $this->getConfigService();
        $showCallLogInstrument = $cfg->isSettingEnabled((int)$project_id, 'show_call_log_instrument', false);
        $showMetadataInstrument = $cfg->isSettingEnabled((int)$project_id, 'show_metadata_instrument', false);

        $hidingCss = [];
        if (!$showMetadataInstrument) {
            $hidingCss[] = '.rc-form-menu-item[data-form="' . $this->instrumentMeta . '"],
div.formMenuList:has(a[id="form[' . $this->instrumentMeta . ']"]),
div.formMenuList:has(a[href*="page=' . $this->instrumentMeta . '"]),
#event_grid_table tr:has([data-mlm-name="' . $this->instrumentMeta . '"]),
#event_grid_table tr:has(a[href*="page=' . $this->instrumentMeta . '"]),
.sysManTable tr:has([data-mlm-name="' . $this->instrumentMeta . '"]),
#record_status_table tr:has([data-mlm-name="' . $this->instrumentMeta . '"]),
#record_status_table tr:has(a[href*="page=' . $this->instrumentMeta . '"]),
div[id*="repeat_instrument_table"][id*="' . $this->instrumentMeta . '"] { display: none !important; }';
        }
        if (!$showCallLogInstrument) {
            $hidingCss[] = '.rc-form-menu-item[data-form="' . $this->instrumentCall . '"],
div.formMenuList:has(a[id="form[' . $this->instrumentCall . ']"]),
div.formMenuList:has(a[href*="page=' . $this->instrumentCall . '"]),
#event_grid_table tr:has([data-mlm-name="' . $this->instrumentCall . '"]),
#event_grid_table tr:has(a[href*="page=' . $this->instrumentCall . '"]),
.sysManTable tr:has([data-mlm-name="' . $this->instrumentCall . '"]),
#record_status_table tr:has([data-mlm-name="' . $this->instrumentCall . '"]),
#record_status_table tr:has(a[href*="page=' . $this->instrumentCall . '"]),
div[id*="repeat_instrument_table"][id*="' . $this->instrumentCall . '"] { display: none !important; }';
        }
        if (!empty($hidingCss)) {
            echo "<style id='callLogVisibilityHider'>" . implode("\n", $hidingCss) . "</style>";
        }

        $this->includeJs('js/utils.js');
        $this->includeJs('js/templates.js');

        if ($this->isPage('ExternalModules/') && ($_GET['prefix'] ?? '') === 'call_log') {
            $page = $_GET['page'] ?? 'index';
            if ($page === 'config') {
                $this->passArgument('rawConfig', $this->getConfigService()->getRawProjectSettings((int)$project_id));
                $this->passArgument('metaInfo', $this->getConfigService()->getProjectMetadataInfo((int)$project_id));
                $this->includeCss('css/config.css');
                $this->includeCss('css/swal.css');
                echo "<script type='text/javascript' src='" . APP_PATH_WEBROOT . "Resources/webpack/css/tinymce/tinymce.min.js'></script>\n";
                $this->includeJs('js/config.js', true);
                $this->includeJs('js/alpine.min.js', true);
            } else {
                // Dependency: ColReorder v1.7.0 paired with REDCap built-in DataTables v1.13.11
                $this->includeCss('css/colReorder.dataTables.min.css');
                $this->includeCss('css/list.css');
                $this->includeCss('css/swal.css');
                $this->includeJs('js/dataTables.colReorder.min.js', true);
                $this->includeJs('js/call_list.js', true);
                $this->includeJs('js/alpine.min.js', true);
                $this->tabsConfig = $this->getConfigService()->getTabConfig((int)$project_id);
                $this->passArgument('tabs', $this->tabsConfig);
                $rawSettings = $this->getConfigService()->getRawProjectSettings((int)$project_id);
                $this->passArgument('dateTimeFormat', $rawSettings['datetime_format'][0] ?? 'm/d/Y g:i A');

                $userSettingsRaw = $this->getUserSetting('user-settings');
                $userSettings = (!empty($userSettingsRaw) && is_string($userSettingsRaw))
                    ? json_decode($userSettingsRaw, true)
                    : (is_array($userSettingsRaw) ? $userSettingsRaw : []);
                $this->passArgument('userSettings', $userSettings ?: (object)[]);
            }
        } elseif ($this->isPage('DataEntry/record_home.php') && !empty($_GET['id'])) {
            $this->includeJs('js/record_home_page.js', 'defer');
        } elseif ($this->isPage('ExternalModules/manager/project.php') && $project_id) {
            $this->includeJs('js/config_modal.js', 'defer');
        }
    }

    public function redcap_data_entry_form($project_id, $record, $instrument)
    {
        $project_id = (int)$project_id;
        $record = (string)$record;
        $rawSummary = $this->getConfigService()->getRawProjectSettings($project_id)['call_summary'] ?? [];
        $summary = is_array($rawSummary) ? $rawSummary : (!empty($rawSummary) ? [$rawSummary] : []);

        $this->passArgument('recentCaller', $this->recentCallStarted($project_id, $record));
        $this->includeJs('js/data_entry.js', true);

        $rawSettings = $this->getConfigService()->getRawProjectSettings($project_id);
        $callNames = [];
        $callScripts = [];
        $callDurations = [];
        foreach ($rawSettings['call_id'] ?? [] as $i => $cid) {
            if (!empty($cid)) {
                $callNames[$cid] = $rawSettings['call_name'][$i] ?? $cid;
                $script = $rawSettings['call_script'][$i] ?? '';
                if (is_array($script)) $script = reset($script);
                $duration = $rawSettings['call_expected_duration'][$i] ?? 30;
                if (is_array($duration)) $duration = reset($duration);
                $callDurations[$cid] = (int)($duration ?: 30);

                $pipedScript = (string)$script;
                if (!empty($pipedScript) && class_exists('Piping')) {
                    $pipedScript = \Piping::replaceVariablesInLabel($pipedScript, $record, null, null, [], false, $project_id);
                }
                $callScripts[$cid] = $pipedScript;
            }
        }
        $this->passArgument('callNames', $callNames);
        $this->passArgument('callScripts', $callScripts);
        $this->passArgument('callDurations', $callDurations);

        $displayNameField = $rawSettings['display_name_field'] ?? '';
        if (is_array($displayNameField)) $displayNameField = reset($displayNameField);
        $participantName = '';
        if (!empty($displayNameField)) {
            $recData = REDCap::getData($project_id, 'array', $record, [$displayNameField]);
            if (is_array($recData[$record] ?? null)) {
                foreach ($recData[$record] as $evData) {
                    if (is_array($evData) && !empty($evData[$displayNameField])) {
                        $participantName = (string)$evData[$displayNameField];
                        break;
                    }
                }
            }
        }
        $this->passArgument('participantName', $participantName);

        if (!empty($_GET['call_id'])) {
            $callId = (string)$_GET['call_id'];
            $metaRepo = $this->getMetadataRepo();
            $metaData = $metaRepo->getMetadata($project_id, $record);
            if (!isset($metaData[$callId]) || !is_array($metaData[$callId])) {
                $metaData[$callId] = ['id' => $callId, 'complete' => false];
            }
            if (empty($metaData[$callId]['name'])) {
                $baseId = explode('|', explode('||', $callId)[0])[0];
                foreach ($rawSettings['call_id'] ?? [] as $i => $cid) {
                    if ($cid === $baseId || $cid === $callId) {
                        $metaData[$callId]['name'] = $rawSettings['call_name'][$i] ?? $callId;
                        $metaData[$callId]['template'] = $rawSettings['call_template'][$i] ?? 'new';
                        break;
                    }
                }
            }
            $user = defined('USERID') ? USERID : '';
            $metaData[$callId]['callStarted'] = date("Y-m-d H:i:s");
            $metaData[$callId]['callStartedBy'] = $user;
            $saved = $metaRepo->saveMetadata($project_id, $record, $metaData);
            if ($saved) {
                $this->getLoggingService()->logCallStarted($project_id, $record, $callId, $user);
            }
        }

        if ($instrument === $this->instrumentCall) {
            $this->passArgument('adhoc', $this->getConfigService()->getAdhocTemplateConfig($project_id));
            $this->includeJs('js/call_log.js', true);
        }

        if (in_array($instrument, array_merge($summary, [$this->instrumentCall]), true)) {
            $metadata = $this->getMetadataRepo()->getMetadata($project_id, $record);
            foreach ($metadata as $k => &$item) {
                if (is_array($item) && empty($item['name'])) {
                    $baseId = explode('|', explode('||', $k)[0])[0];
                    $item['name'] = $callNames[$baseId] ?? ($callNames[$k] ?? ($item['template'] ?? $k));
                }
            }
            unset($item);
            $this->passArgument('metadata', $metadata);
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->includeCss('css/log.css');
            $this->includeCss('css/swal.css');
            $this->includeJs('js/summary_table.js', true);
        }
    }

    public function redcap_module_ajax($action, $payload, $project_id, $record)
    {
        $project_id = (int)$project_id;
        $record = !empty($payload['record']) ? (string)$payload['record'] : (string)($record ?: '');
        $success = true;
        $result = [];
        $callListData = false;

        $metadataRepo = $this->getMetadataRepo();
        $metadataActions = ['metadataSave', 'setCallStarted', 'setCallEnded', 'setNoCallsToday', 'newAdhoc'];
        $metadata = (!empty($record) && in_array($action, $metadataActions, true))
            ? $metadataRepo->getMetadata($project_id, $record)
            : [];

        switch ($action) {
            case "getData":
                $callListRes = $this->getQueryService()->getCallListData($project_id);
                $result['showCallback'] = $callListRes['showCallback'] ?? false;
                $callListData = $callListRes['data'] ?? [];
                break;
            case "deployInstruments":
                $eventId = isset($payload['event_id']) && $payload['event_id'] !== '' ? (int)$payload['event_id'] : null;
                $deployRes = $this->getDeploymentService()->deploy($project_id, __DIR__ . '/call.csv', $eventId);
                $success = $deployRes['success'];
                $result = $deployRes;
                break;
            case "enableRepeatable":
                $eventId = isset($payload['event_id']) && $payload['event_id'] !== '' ? (int)$payload['event_id'] : null;
                $repRes = $this->getDeploymentService()->enableRepeatable($project_id, $eventId);
                $success = $repRes['success'];
                $result = $repRes;
                break;
            case "saveConfig":
                if (!empty($payload['settings']) && is_array($payload['settings'])) {
                    $saved = $this->getConfigService()->saveProjectSettings($project_id, $payload['settings']);
                    $result['saved'] = $saved;
                    if ($saved) {
                        $user = defined('USERID') ? USERID : '';
                        $callTypesCount = count($payload['settings']['call_id'] ?? []);
                        $tabsCount = count($payload['settings']['tab_id'] ?? []);
                        $this->getLoggingService()->logConfigSaved($project_id, $user, [
                            'call_types_count' => $callTypesCount,
                            'tabs_count' => $tabsCount
                        ]);
                    }
                }
                break;
            case "newAdhoc":
                if (!empty($payload['id'])) {
                    $result['saved'] = $this->metadataAdhoc($project_id, $record, $payload);
                }
                break;
            case "callDelete":
                $metadataRepo->deleteLastCallInstance($project_id, $record);
                $user = defined('USERID') ? USERID : '';
                $this->getLoggingService()->logCallInstanceDeleted($project_id, $record, $user);
                break;
            case "metadataSave":
                if (!empty($payload['metadata'])) {
                    $savedData = json_decode($payload['metadata'], true);
                    if (is_array($savedData)) {
                        $result['saved'] = $metadataRepo->saveMetadata($project_id, $record, $savedData);
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
                    $metadata = $metadataRepo->getMetadata($project_id, $record);
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
                    $rawSettings = $this->getConfigService()->getRawProjectSettings($project_id);
                    $baseId = explode('|', explode('||', (string)$targetKey)[0])[0];
                    foreach ($rawSettings['call_id'] ?? [] as $i => $cid) {
                        if ($cid === $baseId || $cid === $targetKey) {
                            $metadata[$targetKey]['name'] = $rawSettings['call_name'][$i] ?? $targetKey;
                            $metadata[$targetKey]['template'] = $rawSettings['call_template'][$i] ?? 'new';
                            $metadata[$targetKey]['id'] = $targetKey;
                            $metadata[$targetKey]['complete'] = false;
                            break;
                        }
                    }
                }
                $startTime = date("Y-m-d H:i:s");
                $metadata[$targetKey]['callStarted'] = $startTime;
                $metadata[$targetKey]['callStartedBy'] = $user;
                $saved = $metadataRepo->saveMetadata($project_id, $record, $metadata);
                $result['saved'] = $saved;
                $result['callStarted'] = $startTime;
                $result['callStartedBy'] = $user;
                if ($saved) {
                    $this->getLoggingService()->logCallStarted($project_id, $record, (string)$targetKey, $user);
                } else {
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
                    $metadata = $metadataRepo->getMetadata($project_id, $record);
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
                $saved = $metadataRepo->saveMetadata($project_id, $record, $metadata);
                $result['saved'] = $saved;
                if ($saved) {
                    $this->getLoggingService()->logCallEnded($project_id, $record, (string)$targetKey, $user);
                } else {
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
                    $metadata = $metadataRepo->getMetadata($project_id, $record);
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
                $saved = $metadataRepo->saveMetadata($project_id, $record, $metadata);
                $result['saved'] = $saved;
                $result['date'] = $todayDate;
                if ($saved) {
                    $this->getLoggingService()->logNoCallsToday($project_id, $record, (string)$targetKey, $user, $todayDate);
                } else {
                    $result['error'] = 'Failed to save no calls today state to metadata.';
                }
                break;
            case "generate":
            case "generateCalls":
            case "newEntryLoad":
            case "scheduleLoad":
                if ($project_id > 0) {
                    $count = $this->getGeneratorService()->evaluateAndGenerateForProject($project_id, 'manual_dashboard');
                    $success = true;
                    $result['generatedCount'] = $count;
                    $result['message'] = "Call log generation completed for project {$project_id}.";
                } else {
                    $success = false;
                    $result['error'] = "Missing or invalid project ID.";
                }
                break;
            case "saveUserColumns":
                $tabId = $payload['tab_id'] ?? '';
                $order = $payload['order'] ?? [];
                $hidden = $payload['hidden'] ?? [];
                if (!empty($tabId)) {
                    $raw = $this->getUserSetting('user-settings');
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
                    $this->setUserSetting('user-settings', json_encode($userSettings));
                    $result['saved'] = true;
                }
                break;
            case "resetUserColumns":
                $tabId = $payload['tab_id'] ?? '';
                if (!empty($tabId)) {
                    $raw = $this->getUserSetting('user-settings');
                    $userSettings = (!empty($raw) && is_string($raw))
                        ? json_decode($raw, true)
                        : (is_array($raw) ? $raw : []);
                    if (is_array($userSettings) && isset($userSettings['tabs'][$tabId])) {
                        unset($userSettings['tabs'][$tabId]);
                        $this->setUserSetting('user-settings', json_encode($userSettings));
                    }
                    $result['saved'] = true;
                }
                break;
        }

        return array_merge([
            "action" => $action,
            "success" => $success,
            "data" => $callListData
        ], $result);
    }

    public function redcap_module_api($action, $payload, $project_id, $user_id, $template_id)
    {
        $projectId = (int)$project_id;
        $payload['action'] = $action;
        $payload['user_id'] = $user_id;
        return $this->getApiService()->handleApiRequest($projectId, $payload);
    }

    public function cronGenerateAllCalls($cronInfo): string
    {
        $projects = $this->getProjectsWithModuleEnabled();
        $totalGenerated = 0;

        foreach ($projects as $projectId) {
            $totalGenerated += $this->getGeneratorService()->evaluateAndGenerateForProject((int)$projectId, 'cron_daily');
        }

        return "Cron completed successfully. Processed calls for " . count($projects) . " projects.";
    }

    public function cronNewEntry($cronInfo): string
    {
        $projects = $this->getProjectsWithModuleEnabled();
        $totalGenerated = 0;

        foreach ($projects as $projectId) {
            $totalGenerated += $this->getGeneratorService()->evaluateAndGenerateForProject((int)$projectId, 'cron_hourly');
        }

        return "Hourly new entry cron completed successfully. Processed calls for " . count($projects) . " projects.";
    }

    private function initGlobal(int $projectId): void
    {
        $this->initializeJavascriptModuleObject();
        $callEvent = $this->getMetadataRepo()->getEventOfInstrument($projectId, $this->instrumentCall);
        $metaEvent = $this->getMetadataRepo()->getEventOfInstrument($projectId, $this->instrumentMeta);
        $username = $this->getUser()->getUsername();

        $cfg = $this->getConfigService();
        $showRecordHomeButton = $cfg->isSettingEnabled($projectId, 'show_record_home_button', true);
        $showCallLogInstrument = $cfg->isSettingEnabled($projectId, 'show_call_log_instrument', false);
        $showMetadataInstrument = $cfg->isSettingEnabled($projectId, 'show_metadata_instrument', false);

        $data = json_encode([
            "eventNameMap" => $this->getConfigService()->getEventNameMap(),
            "prefix" => $this->getPrefix(),
            "user" => $username,
            "userNameMap" => $this->getUserNameMap($projectId),
            "format" => $this->getUserDateFormat($username),
            "dateTimeFormat" => $this->getConfigService()->getRawProjectSettings($projectId)['datetime_format'][0] ?? 'm/d/Y g:i A',
            "static" => [
                "instrument" => $this->instrumentCall,
                "instrumentMeta" => $this->instrumentMeta,
                "instrumentEvent" => $callEvent,
                "metaEvent" => $metaEvent,
                "record_id" => REDCap::getRecordIdField()
            ],
            "workflow" => [
                "showRecordHomeButton" => $showRecordHomeButton,
                "showCallLogInstrument" => $showCallLogInstrument,
                "showMetadataInstrument" => $showMetadataInstrument
            ],
            "configError" => !($callEvent && $metaEvent)
        ]);

        echo "<script>Object.assign({$this->getJavascriptModuleObjectName()}, {$data});</script>";
    }

    private function metadataAdhoc(int $projectId, string $record, array $payload): bool
    {
        $config = $this->getConfigService()->getAdhocTemplateConfig($projectId);
        $adhocConfig = $config[$payload['id']] ?? null;
        if (!$adhocConfig) return false;

        $metadata = $this->getMetadataRepo()->getMetadata($projectId, $record);
        $date = !empty($payload['date']) ? $payload['date'] : date('Y-m-d');
        $time = $payload['time'] ?? '00:00';
        $reported = date('Y-m-d H:i:s');
        $reporter = $this->getUserNameMap($projectId)[$payload['reporter'] ?? ''] ?? ($payload['reporter'] ?? '');

        $key = $adhocConfig['id'] . '||' . $reported;
        $metadata[$key] = [
            "start" => $date,
            "contactOn" => trim("{$date} {$time}"),
            "reported" => $reported,
            "reporter" => $reporter,
            "reason" => $payload['reason'] ?? '',
            "initNotes" => $payload['notes'] ?? '',
            "template" => 'adhoc',
            "event_id" => '',
            "event" => '',
            "name" => $adhocConfig['name'] . ' - ' . ($adhocConfig['reasons'][$payload['reason'] ?? ''] ?? ''),
            "instances" => [],
            "voiceMails" => 0,
            "hideAfterAttempt" => $adhocConfig['hideAfterAttempt'] ?? 9999,
            "complete" => false
        ];

        $saved = $this->getMetadataRepo()->saveMetadata($projectId, $record, $metadata);
        if ($saved) {
            $this->getLoggingService()->logAdhocCreated(
                $projectId,
                $record,
                $key,
                $adhocConfig['name'] . ' - ' . ($adhocConfig['reasons'][$payload['reason'] ?? ''] ?? ''),
                (string)($payload['reason'] ?? ''),
                $reporter,
                'ui',
                [
                    'contact_date' => $date,
                    'contact_time' => $time
                ]
            );
        }
        return $saved;
    }



    private function getAllCallData(int $projectId, string $record): array
    {
        $event = $this->getMetadataRepo()->getEventOfInstrument($projectId, $this->instrumentCall);
        $data = REDCap::getData($projectId, 'array', $record, null, $event);
        $callData = $data[$record]['repeat_instances'][$event][$this->instrumentCall] ?? [];
        return empty($callData) ? [1 => ($data[$record][$event] ?? [])] : $callData;
    }

    private function recentCallStarted(int $projectId, string $record): string
    {
        $meta = $this->getMetadataRepo()->getMetadata($projectId, $record);
        if (empty($meta)) return '';

        try {
            $userObj = $this->getUser();
            $user = $userObj ? $userObj->getUsername() : (defined('USERID') ? USERID : '');
        } catch (\Throwable $e) {
            $user = defined('USERID') ? USERID : '';
        }
        foreach ($meta as $call) {
            if (
                empty($call['complete']) &&
                (($call['callStartedBy'] ?? '') !== $user) &&
                !empty($call['callStarted']) &&
                ((time() - strtotime($call['callStarted'])) / 60 < $this->startedCallGrace)
            ) {
                return $call['callStartedBy'];
            }
        }
        return '';
    }

    private function getUserNameMap(int $projectId): array
    {
        $map = [];
        $sql = "
        SELECT u.username, u.full_name FROM redcap_user_rights ur
        LEFT JOIN (SELECT
            lower(trim(i.username)) AS username,
            trim(concat(i.user_firstname, ' ', i.user_lastname)) AS full_name
        FROM redcap_user_information i
        WHERE i.username != '') u ON ur.username = u.username
        WHERE project_id = ?";
        $result = $this->query($sql, [$projectId]);
        while ($row = $result->fetch_assoc()) {
            $map[$row['username']] = $row['full_name'];
        }
        return $map;
    }

    private function getUserDateFormat(string $username): array
    {
        $sql = "SELECT datetime_format FROM redcap_user_information WHERE username = ?";
        $result = $this->query($sql, [$username]);
        $row = $result->fetch_assoc();
        $format = $row['datetime_format'] ?? 'Y-M-D_24';
        [$date, $time] = array_pad(explode('_', $format), 2, '24');
        $time = $time === '12' ? 'hh:mma' : 'HH:mm';
        $date = str_replace(['Y', 'M', 'D'], ['y', 'MM', 'dd'], $date);
        return [
            'date' => $date,
            'dateTime' => "{$date} {$time}"
        ];
    }

    public function includeJs(string $path, bool $defer = false, bool $module = false): void
    {
        $defer = $defer ? "defer " : "";
        $module = $module ? "type=\"module\" " : "";
        echo '<script ' . $module . $defer . 'src="' . $this->getUrl($path) . '"></script>';
    }

    public function includeCss(string $path): void
    {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '"/>';
    }

    private function passArgument(string $name, $value): void
    {
        echo "<script>" . $this->getJavascriptModuleObjectName() . "." . $name . " = " . json_encode($value) . ";</script>";
    }
}
