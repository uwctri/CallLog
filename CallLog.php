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

class CallLog extends AbstractExternalModule
{
    public array $tabsConfig = [];

    private string $instrumentCall = "call_log";
    private string $instrumentMeta = "call_log_metadata";
    private string $metadataField = "call_metadata";
    private int $startedCallGrace = 30;

    private ?ConfigService $configService = null;
    private ?CallMetadataRepository $metadataRepo = null;
    private ?DateMathService $dateMathService = null;
    private ?CallGeneratorService $generatorService = null;
    private ?CallQueryService $queryService = null;
    private ?InstrumentDeploymentService $deploymentService = null;
    private ?ApiService $apiService = null;

    public function getConfigService(): ConfigService
    {
        return $this->configService ??= new ConfigService($this);
    }

    private function getMetadataRepo(): CallMetadataRepository
    {
        return $this->metadataRepo ??= new CallMetadataRepository();
    }

    private function getDateMathService(): DateMathService
    {
        return $this->dateMathService ??= new DateMathService();
    }

    private function getGeneratorService(): CallGeneratorService
    {
        return $this->generatorService ??= new CallGeneratorService(
            $this,
            $this->getConfigService(),
            $this->getMetadataRepo(),
            $this->getDateMathService()
        );
    }

    private function getQueryService(): CallQueryService
    {
        return $this->queryService ??= new CallQueryService(
            $this,
            $this->getConfigService(),
            $this->getMetadataRepo()
        );
    }

    private function getDeploymentService(): InstrumentDeploymentService
    {
        return $this->deploymentService ??= new InstrumentDeploymentService();
    }

    private function getApiService(): ApiService
    {
        return $this->apiService ??= new ApiService(
            $this,
            $this->getConfigService(),
            $this->getGeneratorService(),
            $this->getMetadataRepo()
        );
    }

    public function redcap_save_record($project_id, $record, $instrument)
    {
        $project_id = (int)$project_id;
        $record = (string)$record;

        $this->getGeneratorService()->evaluateAndGenerateForRecord($project_id, $record, $instrument);
    }

    public function redcap_every_page_top($project_id)
    {
        if (!defined("USERID")) return;

        try {
            $this->initGlobal($project_id);
        } catch (\Throwable $e) {
            return;
        }

        $this->includeJs('js/templates.js');

        if ($this->isPage('ExternalModules/') && ($_GET['prefix'] ?? '') === 'call_log') {
            $page = $_GET['page'] ?? 'index';
            if ($page === 'config') {
                $this->passArgument('rawConfig', $this->getConfigService()->getRawProjectSettings((int)$project_id));
                $this->passArgument('metaInfo', $this->getConfigService()->getProjectMetadataInfo((int)$project_id));
                $this->includeCss('css/config.css');
                $this->includeJs('js/config.js', true);
                $this->includeJs('js/alpine.min.js', true);
            } else {
                $this->includeCss('css/list.css');
                $this->includeJs('js/call_list.js', true);
                $this->includeJs('js/alpine.min.js', true);
                $this->tabsConfig = $this->getConfigService()->getTabConfig((int)$project_id);
                $this->passArgument('tabs', $this->tabsConfig);
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
        $summary = $this->getConfigService()->getRawProjectSettings($project_id)['call_summary'];

        $this->passArgument('recentCaller', $this->recentCallStarted($project_id, $record));
        $this->includeJs('js/data_entry.js', 'defer');

        if ($instrument === $this->instrumentCall) {
            $this->passArgument('adhoc', $this->getConfigService()->getAdhocTemplateConfig($project_id));
            $this->includeJs('js/call_log.js', 'defer');
        }

        if (in_array($instrument, array_merge($summary, [$this->instrumentCall]), true)) {
            $this->passArgument('metadata', $this->getMetadataRepo()->getMetadata($project_id, $record));
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->includeCss('css/log.css');
            $this->includeJs('js/summary_table.js');
        }
    }

    public function redcap_module_ajax($action, $payload, $project_id, $record)
    {
        $project_id = (int)$project_id;
        $record = (string)($record ?? $payload['record'] ?? '');
        $success = true;
        $result = [];
        $callListData = false;

        $metadataRepo = $this->getMetadataRepo();
        $metadata = $metadataRepo->getMetadata($project_id, $record);

        switch ($action) {
            case "log":
                $this->projectLog($payload['text'] ?? '', $record, $payload['event'] ?? null, $project_id);
                break;
            case "getData":
                $callListData = $this->getQueryService()->getCallListData($project_id);
                break;
            case "deployInstruments":
                $eventId = isset($payload['event_id']) && $payload['event_id'] !== '' ? (int)$payload['event_id'] : null;
                $deployRes = $this->getDeploymentService()->deploy($project_id, __DIR__ . '/call.csv', $eventId);
                $success = $deployRes['success'];
                $result = $deployRes;
                break;
            case "saveConfig":
                if (!empty($payload['settings']) && is_array($payload['settings'])) {
                    $result['saved'] = $this->getConfigService()->saveProjectSettings($project_id, $payload['settings']);
                }
                break;
            case "newAdhoc":
                if (!empty($payload['id'])) {
                    $result = $this->metadataAdhoc($project_id, $record, $payload);
                }
                break;
            case "callDelete":
                $metadataRepo->deleteLastCallInstance($project_id, $record);
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
                if (!empty($payload['id']) && !empty($payload['user']) && !empty($metadata)) {
                    $metadata[$payload['id']]['callStarted'] = date("Y-m-d H:i");
                    $metadata[$payload['id']]['callStartedBy'] = $payload['user'];
                    $result['saved'] = $metadataRepo->saveMetadata($project_id, $record, $metadata);
                }
                break;
            case "setCallEnded":
                if (!empty($payload['id']) && !empty($metadata)) {
                    $metadata[$payload['id']]['callStarted'] = '';
                    $result['saved'] = $metadataRepo->saveMetadata($project_id, $record, $metadata);
                }
                break;
            case "setNoCallsToday":
                if (!empty($payload['id']) && !empty($metadata)) {
                    if (!is_array($metadata[$payload['id']]['noCallsToday'] ?? null)) {
                        $metadata[$payload['id']]['noCallsToday'] = [];
                    }
                    $metadata[$payload['id']]['noCallsToday'][] = date('Y-m-d');
                    $result['saved'] = $metadataRepo->saveMetadata($project_id, $record, $metadata);
                }
                break;
            case "generate":
            case "generateCalls":
            case "newEntryLoad":
            case "scheduleLoad":
                if ($project_id > 0) {
                    $count = $this->getGeneratorService()->evaluateAndGenerateForProject($project_id);
                    $success = true;
                    $result['generatedCount'] = $count;
                    $result['message'] = "Call log generation completed for project {$project_id}.";
                } else {
                    $success = false;
                    $result['error'] = "Missing or invalid project ID.";
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
            $totalGenerated += $this->getGeneratorService()->evaluateAndGenerateForProject((int)$projectId);
        }

        return "Cron completed successfully. Processed calls for " . count($projects) . " projects.";
    }

    public function cronNewEntry($cronInfo): string
    {
        $projects = $this->getProjectsWithModuleEnabled();
        $totalGenerated = 0;

        foreach ($projects as $projectId) {
            $totalGenerated += $this->getGeneratorService()->evaluateAndGenerateForProject((int)$projectId);
        }

        return "Hourly new entry cron completed successfully. Processed calls for " . count($projects) . " projects.";
    }

    private function initGlobal(int $projectId): void
    {
        $this->initializeJavascriptModuleObject();
        $callEvent = $this->getMetadataRepo()->getEventOfInstrument($projectId, $this->instrumentCall);
        $metaEvent = $this->getMetadataRepo()->getEventOfInstrument($projectId, $this->instrumentMeta);
        $username = $this->getUser()->getUsername();

        $data = json_encode([
            "eventNameMap" => $this->getConfigService()->getEventNameMap(),
            "prefix" => $this->getPrefix(),
            "user" => $username,
            "userNameMap" => $this->getUserNameMap($projectId),
            "format" => $this->getUserDateFormat($username),
            "static" => [
                "instrument" => $this->instrumentCall,
                "instrumentEvent" => $callEvent,
                "record_id" => REDCap::getRecordIdField()
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

        return $this->getMetadataRepo()->saveMetadata($projectId, $record, $metadata);
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

        $user = $this->getUser()->getUsername();
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
