<?php

namespace UWMadison\CallLog;

use ExternalModules\AbstractExternalModule;
use REDCap;
use Project;
use Piping;
use Design;
use Metadata;

require_once 'traits/BasicCallActions.php';
require_once 'traits/CallLogic.php';
require_once 'traits/Utility.php';

class CallLog extends AbstractExternalModule
{
    use CallLogic;
    use Utility;

    private $module_global = 'CallLog';

    // Hard Coded Data Dictionary Values
    public $instrumentName = "Call Log";
    public $instrumentLower = "call_log";
    public $instrumentMeta = "call_log_metadata";
    public $metadataField = "call_metadata";

    // Hard Coded Config
    public $startedCallGrace = '30'; # mins to start a call early
    public $dateMathCutoff = 5; # Date+/- less than N days will avoid weekends/holidays
    public $holidays = ['12-25', '12-24', '12-31', '07-04', '01-01']; # Fixed holidays

    public function redcap_save_record($project_id, $record, $instrument)
    {
        if ($instrument == $this->instrumentLower) {
            $this->reportDisconnectedPhone($project_id, $record);
            $this->metadataUpdateCommon($project_id, $record);
            return;
        }
        $metadata = $this->getCallMetadata($project_id, $record);
        $config = $this->loadCallTemplateConfig();
        $triggerForm = $this->getProjectSetting('trigger_save');
        if (empty($triggerForm) || ($instrument == $triggerForm)) {
            $this->metadataFollowup($project_id, $record, $metadata, $config['followup']);
            $this->metadataReminder($project_id, $record, $metadata, $config['reminder']);
            $this->metadataMissedCancelled($project_id, $record, $metadata, $config['mcv']);
            $this->metadataNeedToSchedule($project_id, $record, $metadata, $config['nts']);
        }
        $this->updateDisconnectedPhone($project_id, $record);
        $this->metadataNewEntry($project_id, $record, $metadata, $config['new']);
        $this->metadataPhoneVisit($project_id, $record, $metadata, $config['visit']);
        $this->callStarted($project_id, $record, $metadata);
    }

    public function redcap_every_page_top($project_id)
    {
        if (!defined("USERID")) return;

        include('templates.php');
        $this->initGlobal();
        $this->includeJs('js/every_page.js');

        // Record Home Page
        if ($this->isPage('DataEntry/record_home.php') && $_GET['id']) {
            $this->includeJs('js/record_home_page.js');
        }

        // Custom Config page
        if ($this->isPage('ExternalModules/manager/project.php') && $project_id) {
            $this->includeCss('css/config.css');
            $this->includeJs('js/config.js');
        }
    }

    public function redcap_data_entry_form($project_id, $record, $instrument)
    {
        $summary = $this->getProjectSetting('call_summary');
        $this->passArgument('recentCaller', $this->recentCallStarted($project_id, $record));

        // Call Log only info
        if ($instrument == $this->instrumentLower) {
            $this->passArgument('adhoc', $this->loadAdhocTemplateConfig());
            $this->includeJs('js/call_log.js');
        }

        // Call Log + anything w/ summary table on it
        if (in_array($instrument, array_merge($summary, [$this->instrumentLower]))) {
            $this->passArgument('metadata', $this->getCallMetadata($project_id, $record));
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->includeCss('css/log.css');
            $this->includeJs('js/summary_table.js');
        }
    }

    public function redcap_module_link_check_display($project_id, $link)
    {
        return $link;
    }

    public function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id)
    {
        // TODO this isn't used currently
        // TODO port over "logs" action to normal log method

        $callListData = false;
        $saveResult = [];
        $metadata = $this->getCallMetadata($project_id, $record);
        $config = $this->loadCallTemplateConfig();

        switch ($action) {
            case "getData":
                // Load for the Call List
                $callListData = $this->loadCallListData();
                break;
            case "newAdhoc":
                # Intended to be posted to by an outside script or DET to make a singe new adhoc call on a record
                # API url: ExternalModules/?prefix=call_log&page=router&route=newAdhoc&pid=NNN&adhocCode=NNN&record=NNN&type=NNN&fudate=NNN&futime=NNN&reporter=NAME
                # Identical to adhocLoad but via GET, seperated for possible future changes
                if (empty($_GET['type'])) break;
                $saveResult = $this->metadataAdhoc($project_id, $record, $config["adhoc"], [
                    'id' => $_GET['type'],
                    'date' => $_GET['fudate'],
                    'time' => $_GET['futime'],
                    'reason' => $_GET['adhocCode'],
                    'reporter' => $_GET['reporter']
                ]);
                break;
            case "adhocLoad":
                # Posted to by the call log to save a new adhoc call
                if (empty($_POST['id'])) break;
                $saveResult = $this->metadataAdhoc($project_id, $record, $config["adhoc"], [
                    'id' => $_POST['id'],
                    'date' => $_POST['date'],
                    'time' => $_POST['time'],
                    'reason' => $_POST['reason'],
                    'notes' => $_POST['notes'],
                    'reporter' => $_POST['reporter']
                ]);
                break;
            case "adhocResolve":
                # Intended to be posted to by an outside script or DET to resolve an existing adhoc call on a record(s)
                # API url: /ExternalModules/?prefix=call_log&page=router&route=adhocResolve&pid=NNN&adhocCode=NNN&recordList=NNN
                if (empty($_GET['adhocCode'])) break;
                foreach (explode(',', $record) as $rcrd) {
                    $this->resolveAdhoc($project_id, trim($rcrd), $_GET['adhocCode'], $metadata);
                }
                break;
            case "callDelete":
                # Delete instance of log from the summary table on call log
                $this->deleteLastCallInstance($project_id, $record, $metadata);
                break;
            case "metadataSave":
                # Posted to by the call log to save the record's data via the console.
                # This is useful for debugging and resolving enduser issues.
                if (empty($_POST['metadata'])) break;
                $saveResult = $this->saveCallMetadata($project_id, $record, json_decode($_POST['metadata'], true));
                break;
            case "newEntryLoad":
                # This page is intended to be posted to by an outside script to load New Entry calls for any number of records
                # API url: /ExternalModules/?prefix=call_log&page=router&route=newEntryLoad&pid=NNN&recordList=NNN
                foreach (explode(',', $record) as $rcrd) {
                    $this->metadataNewEntry($project_id, trim($rcrd), $metadata, $config['new']);
                }
                break;
            case "scheduleLoad":
                # This page is intended to be posted to by an outside script after scheduling occurs. 
                # API url: /ExternalModules/?prefix=call_log&page=router&route=scheduleLoad&pid=NNN&recordList=NNN
                foreach (explode(',', $record) as $rcrd) {
                    $this->metadataReminder($project_id, trim($rcrd), $metadata, $config['reminder']);
                    $this->metadataMissedCancelled($project_id, trim($rcrd), $metadata, $config['mcv']);
                    $this->metadataNeedToSchedule($project_id, trim($rcrd), $metadata, $config['nts']);
                }
                break;
            case "setCallEnded":
                # This page is posted to by the call list to flag a call as no longer in progress
                if (empty($_POST['id']) || empty($metadata)) break;
                $saveResult = $this->callEnded($project_id, $record, $metadata, $_POST['id']);
                break;
            case "setCallStarted":
                # This page is posted to by the call list to flag a call as in progress
                if (empty($_POST['id']) || empty($_POST['user']) || empty($metadata)) break;
                $saveResult = $this->callStarted($project_id, $record, $metadata, $_POST['id'], $_POST['user']);
                break;
            case "setNoCallsToday":
                # This page is posted to by the call list to flag a call as "no calls today"
                if (empty($_POST['id'])) break;
                $saveResult = $this->noCallsToday($project_id, $record, $metadata, $_POST['id']);
                break;
        }

        return  array_merge([
            "text" => "Action '$action' was completed successfully",
            "success" => true,
            "data" => $callListData
        ], $saveResult);
    }

    private function deployInstruments($project_id)
    {
        // Check if deployed
        $instruments = array_keys(REDCap::getInstrumentNames(null, $project_id));
        if (in_array($this->instrumentLower, $instruments)) return;
        if (in_array($this->instrumentMeta, $instruments)) return;

        // Prep to correct the dd
        global $Proj;
        $Proj = new Project($project_id);
        $dd = Design::excel_to_array($this->getUrl("call.csv"), ",");
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        //Create a data dictionary snapshot of the *current* metadata and store the file in the edocs table
        // This is why we need to define a Project above
        MetaData::createDataDictionarySnapshot();

        // Update the dd
        $sql_errors = MetaData::save_metadata($dd, true, false, $project_id);
        db_query(count($sql_errors) > 0 ? "ROLLBACK" : "COMMIT");
        db_query("SET AUTOCOMMIT=1");
    }

    function cronNeedToSchedule($cronInfo)
    {
        global $Proj;
        $today = Date('Y-m-d');

        foreach ($this->getProjectsWithModuleEnabled() as $project_id) {
            $_GET['pid'] = $project_id;
            $Proj = new Project($project_id);
            $project_record_id = $this->getRecordIdField($project_id);

            $config = $this->loadCallTemplateConfig()["nts"];
            if (empty($config))
                continue;
            foreach ($config as $callConfig) {

                $fields = [$project_record_id, $callConfig['apptDate'], $callConfig['indicator'], $callConfig['skip'], $callConfig["window"]];
                $project_data = REDCap::getData($project_id, 'array', null, $fields);

                foreach ($project_data as $record => $data) {

                    $meta = $this->getCallMetadata($project_id, $record);
                    $event = $callConfig['event'];
                    // Call ID already exists
                    if ($meta[$callConfig["id"]])
                        continue;
                    // Appt is scheuled, bail
                    if ($data[$event][$callConfig['apptDate']] != "")
                        continue;
                    // Skip flag is set
                    if ($data[$event][$callConfig['skip']])
                        continue;
                    // Appt has already been attended
                    if ($data[$event][$callConfig['indicator']])
                        continue;

                    if ($data[$event][$callConfig["window"]] >= $today) {
                        $meta[$callConfig['id']] = [
                            "template" => 'nts',
                            "event_id" => $event,
                            "name" => $callConfig['name'],
                            "instances" => [],
                            "voiceMails" => 0,
                            "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                            "complete" => false
                        ];
                        $this->projectLog("Need to Schedue call {$callConfig['id']} created during cron", $record, $event, $project_id);
                    }
                }
            }
        }
        return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
    }

    public function metadataAdhoc($project_id, $record, $config, $payload)
    {
        $config = array_filter($config, function ($x) use ($payload) {
            return $x['id'] == $payload['id'];
        });
        $config = end($config);
        $metadata = $this->getCallMetadata($project_id, $record);
        if (empty($payload['date']))
            $payload['date'] = Date('Y-m-d');
        $reported = Date('Y-m-d H:i:s');
        $metadata[$config['id'] . '||' . $reported] = [
            "start" => $payload['date'],
            "contactOn" => trim($payload['date'] . " " . $payload['time']),
            "reported" => $reported,
            "reporter" => $this->getUserNameMap($project_id)[$payload['reporter']],
            "reason" => $payload['reason'],
            "initNotes" => $payload['notes'],
            "template" => 'adhoc',
            "event_id" => '',
            "event" => '',
            "name" => $config['name'] . ' - ' . $config['reasons'][$payload['reason']],
            "instances" => [],
            "voiceMails" => 0,
            "hideAfterAttempt" => $config['hideAfterAttempt'],
            "complete" => false
        ];
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }

    public function resolveAdhoc($project_id, $record, $code, $metadata)
    {
        foreach ($metadata as $callID => $callData) {
            if ($callData['complete'] || $callData['reason'] != $code) continue;
            $callData['complete'] = true; // Don't delete, just comp. Might need info for something
            $callData['completedBy'] = "REDCap";
            $this->projectLog("Adhoc call {$callID} marked as complete via API.", $record, null, $project_id);
        }
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }

    public function deleteLastCallInstance($project_id, $record, $metadata)
    {
        $event = $this->getEventOfInstrument('call_log');
        $data = REDCap::getData($project_id, 'array', $record, null, $event);
        $instance = end(array_keys($data[$record]['repeat_instances'][$event][$this->instrumentLower]));
        $instance = $instance ? $instance : '1';
        $instanceText = $instance != '1' ? ' AND instance= ' . $instance : ' AND isnull(instance)';
        foreach ($metadata as $index => $call) {
            $tmp = $metadata[$index]['instances'];
            $metadata[$index]['instances'] = array_values(array_diff($call['instances'], array($instance)));
            // If we did remove a call then make sure we mark the call as incomplete.
            // We currently don't allow completed calls to actually be deleted though as there could be unforeseen issues.
            if ((count($tmp) > 0) && (count($tmp) != count($metadata[$index]['instances'])))
                $metadata[$index]['complete'] = false;
        }
        $fields = array_values(array_intersect(REDCap::getFieldNames($this->instrumentLower), array_keys($data[$record][$event])));
        db_query('DELETE FROM redcap_data WHERE project_id=' . $project_id . ' AND record=' . $record . $instanceText . ' AND (field_name="' . implode('" OR field_name="', $fields) . '");');
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }

    public function getAllCallData($project_id, $record)
    {
        $event = $this->getEventOfInstrument('call_log');
        $data = REDCap::getData($project_id, 'array', $record, null, $event);
        $callData = $data[$record]['repeat_instances'][$event][$this->instrumentLower];
        return empty($callData) ? [1 => $data[$record][$event]] : $callData;
    }

    public function getCallMetadata($project_id, $record)
    {
        $metadata = REDCap::getData($project_id, 'array', $record, $this->metadataField)[$record][$this->getEventOfInstrument('call_log_metadata')][$this->metadataField];
        return empty($metadata) ? [] : json_decode($metadata, true);
    }

    public function saveCallMetadata($project_id, $record, $data)
    {
        return REDCap::saveData(
            $project_id,
            'array',
            [
                $record => [
                    $this->getEventOfInstrument('call_log_metadata') => [
                        $this->metadataField => json_encode($data)
                    ]
                ]
            ]
        );
    }

    public function recentCallStarted($project_id, $record)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (empty($meta)) return '';
        $grace = $this->startedCallGrace; // Minutes of Grace time
        $user = $this->framework->getUser()->getUsername();
        foreach ($meta as $call) {
            if (
                !$call['complete'] && ($call['callStartedBy'] != $user) &&
                !empty($call['callStarted']) && ((time() - strtotime($call['callStarted']) / 60) < $grace)
            ) {
                return $call['callStartedBy'];
            }
        }
        return '';
    }

    public function loadBadPhoneConfig()
    {
        $settings = $this->getProjectSettings();
        $config = [
            $settings['bad_phone_event'][0], $settings['bad_phone_flag'][0],
            $settings['bad_phone_notes'][0], $settings['bad_phone_resolved'][0]
        ];
        $missing = count(array_filter($config)) != count($config);
        return [
            'event' => $config[0],
            'flag' => $config[1],
            'notes' => $config[2],
            'resolved' => $config[3],
            '_missing' => $missing
        ];
    }

    public function loadAdhocTemplateConfig()
    {
        $settings = $this->getProjectSettings();
        foreach ($settings["call_template"] as $i => $template) {
            if ($template != "adhoc") continue;
            $reasons = $settings["adhoc_reason"][$i][0];
            if (empty($reasons)) continue;
            $config[$settings["call_id"][$i]] = [
                "id" => $settings["call_id"][$i],
                "name" => $settings["call_name"][$i],
                "reasons" => $this->explodeCodedValueText($reasons)
            ];
        }
        return $config;
    }

    public function loadCallTemplateConfig()
    {
        $eventNameMap = $this->getEventNameMap();
        $newEntryConfig = [];
        $reminderConfig = [];
        $followupConfig = [];
        $mcvConfig = [];
        $ntsConfig = [];
        $adhocConfig = [];
        $visitConfig = [];
        $settings = $this->getProjectSettings();
        foreach ($settings["call_template"] as $i => $template) {
            $hide = $settings["hide_after_attempts"][$i];
            $commonConfig = [
                "id" => $settings["call_id"][$i],
                "name" => $settings["call_name"][$i],
                "hideAfterAttempt" => $hide ? (int)$hide : 9999
            ];

            // Load New Entry Config
            if ($template == "new") {
                $days = intval($settings["new_expire_days"][$i][0]);
                $arr = array_merge([
                    "expire" => $days
                ], $commonConfig);
                $newEntryConfig[] = $arr;
            }

            // Load Reminder Config
            elseif ($template == "reminder") {
                $field = $settings["reminder_variable"][$i][0];
                if (empty($field)) continue;
                $includeEvents = array_map('trim', explode(',', $settings["reminder_include_events"][$i][0]));
                foreach ($includeEvents as $eventName) {
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "field" => $field,
                        "days" => (int)$settings["reminder_days"][$i][0],
                        "removeEvent" => $settings["reminder_remove_event"][$i][0],
                        "removeVar" => $settings["reminder_remove_var"][$i][0]
                    ], $commonConfig);
                    $arr['id'] = $arr['id'] . '|' . $eventName;
                    $arr['name'] = $arr['name'] . ' - ' . $eventNameMap[$eventName];
                    $reminderConfig[] = $arr;
                }
            }

            // Load Follow up Config
            elseif ($template == "followup") {
                $event = $settings["followup_event"][$i][0];
                $field = $settings["followup_date"][$i][0];
                $days = (int)$settings["followup_days"][$i][0];
                $length = (int)$settings["followup_length"][$i][0];
                $end = $settings["followup_end"][$i][0];
                if (!empty($field) && !empty($event) && !empty($days)) {
                    $followupConfig[] = array_merge([
                        "event" => $event,
                        "field" => $field,
                        "days" => $days,
                        "length" => $length,
                        "end" => $end
                    ], $commonConfig);
                } elseif (!empty($field) && (!empty($days) || $days == "0")) {
                    $includeEvents = array_map('trim', explode(',', $settings["followup_include_events"][$i][0]));
                    foreach ($includeEvents as $eventName) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "field" => $field,
                            "days" => $days,
                            "length" => $length,
                            "end" => $end
                        ], $commonConfig);
                        $arr['id'] = $arr['id'] . '|' . $eventName;
                        $arr['name'] = $arr['name'] . ' - ' . $eventNameMap[$eventName];
                        $followupConfig[] = $arr;
                    }
                }
            }

            // Load Missed/Cancelled Visit Config
            elseif ($template == "mcv") {
                $indicator = $settings["mcv_indicator"][$i][0];
                $dateField = $settings["mcv_date"][$i][0];
                if (empty($indicator) || empty($dateField)) continue;
                $includeEvents = array_map('trim', explode(',', $settings["mcv_include_events"][$i][0]));
                foreach ($includeEvents as $eventName) {
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "indicator" => $indicator,
                        "apptDate" => $dateField,
                    ], $commonConfig);
                    $arr['id'] = $arr['id'] . '|' . $eventName;
                    $arr['name'] = $arr['name'] . ' - ' . $eventNameMap[$eventName];
                    $mcvConfig[] = $arr;
                }
            }

            // Load Need to Schedule Visit Config
            elseif ($template == "nts") {
                $indicator = $settings["nts_indicator"][$i][0];
                $dateField = $settings["nts_date"][$i][0];
                $skipField = $settings["nts_skip"][$i][0];
                $window = $settings["nts_window_start_cron"][$i][0];
                if (empty($indicator) || empty($dateField)) continue;
                $includeEvents = array_map('trim', explode(',', $settings["nts_include_events"][$i][0]));
                foreach ($includeEvents as $eventName) {
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "indicator" => $indicator,
                        "apptDate" => $dateField,
                        "skip" => $skipField,
                        "window" => $window
                    ], $commonConfig);
                    $arr['id'] = $arr['id'] . '|' . $eventName;
                    $arr['name'] = $arr['name'] . ' - ' . $eventNameMap[$eventName];
                    $ntsConfig[] = $arr;
                }
            }

            // Load Adhoc Visit Config
            elseif ($template == "adhoc") {
                $reasons = $settings["adhoc_reason"][$i][0];
                if (empty($reasons)) continue;
                $arr = array_merge([
                    "reasons" => $this->explodeCodedValueText($reasons),
                ], $commonConfig);
                $adhocConfig[] = $arr;
            }

            // Load Scheduled Phone Visit Config
            elseif ($template == "visit") {
                $indicator = $settings["visit_indicator"][$i][0];
                $autoField = $settings["visit_auto_remove"][$i][0];
                if (empty($indicator)) continue;
                $includeEvents = array_map('trim', explode(',', $settings["visit_include_events"][$i][0]));
                foreach ($includeEvents as $eventName) {
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "indicator" => $indicator,
                        "autoRemove" => $autoField
                    ], $commonConfig);
                    $arr['id'] = $arr['id'] . '|' . $eventName;
                    $arr['name'] = $arr['name'] . ' - ' . $eventNameMap[$eventName];
                    $visitConfig[] = $arr;
                }
            }
        }
        return [
            "new" => $newEntryConfig,
            "reminder" => $reminderConfig,
            "followup" => $followupConfig,
            "mcv" => $mcvConfig,
            "nts" => $ntsConfig,
            "adhoc" => $adhocConfig,
            "visit" => $visitConfig
        ];
    }

    public function loadAutoRemoveConfig()
    {
        $settings = $this->getProjectSettings();
        $config = [];
        foreach ($settings["call_template"] as $i => $template) {
            if ($template == "mcv")
                $config[$settings["call_id"][$i]] = $settings["mcv_auto_remove"][$i][0];
            if ($template == "visit")
                $config[$settings["call_id"][$i]] = $settings["visit_auto_remove"][$i][0];
            if ($template == "followup")
                $config[$settings["call_id"][$i]] = $settings["followup_auto_remove"][$i][0];
        }
        return $config;
    }

    public function loadTabConfig()
    {
        // Grab setup
        global $Proj;
        $allFields = [];
        $settings = $this->getProjectSettings();
        $orderMapping = $settings["tab_order"];
        $record_id_field = REDCap::getRecordIdField();
        $record_id_label = $this->getFieldLabel($record_id_field);
        $dd = REDCap::getDataDictionary('array');

        // Grab all expanded area info that is the same across tabs
        $expands = [];
        foreach ($settings["tab_expands_field"][0] as $i => $field) {
            $name = $settings["tab_expands_field_name"][0][$i] ?? trim($this->getFieldLabel($field), ":?");
            $validation = $Proj->metadata[$field]["element_validation_type"] ?? "";
            $default = $settings["tab_expands_field_default"][0][$i] ?? "";
            $expands[] = [
                "field" => $field,
                "map" => $this->getDictionaryValuesFor($field, $dd),
                "displayName" => trim($name) . ": ",
                "validation" => $validation,
                "isFormStatus" => $Proj->isFormStatus($field),
                "fieldType" => $Proj->metadata[$field]["element_type"],
                "default" => $default,
                "expanded" => true
            ];
            $allFields[] = $field;
        }

        // Default ordering
        if (count(array_filter($settings["tab_order"])) != count($settings["tab_order"])) {
            $orderMapping = range(0, count($settings["tab_name"]));
        }

        // Loop over all tabs
        foreach ($settings["tab_name"] as $i => $tab_name) {

            // Grap settings for the tab
            $tabOrder = $orderMapping[$i];
            $calls = $settings["tab_calls_included"][$i];
            $tab_id = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '_', strtolower($tab_name)));
            $tabConfig[$tabOrder] = [
                "tab_name" => $tab_name,
                "included_calls" => $calls,
                "tab_id" => $tab_id,
                "fields" => $settings["tab_field"][$i],
                "showFollowupWindows" => $settings["tab_includes_followup"][$i] == '1',
                "showMissedDateTime" => $settings["tab_includes_mcv"][$i] == '1',
                "showAdhocDates" => $settings["tab_includes_adhoc"][$i] == '1'
            ];
            $tabNameMap[$tab_id] = $tab_name;
            $calls = array_map('trim', explode(',', $calls));
            foreach ($calls as $call) {
                $call2TabMap[$call] = $tab_id;
            }

            // Setup standard & shared fields
            $tabConfig[$tabOrder]["fields"] = array_merge($expands, [
                [
                    "field" => $record_id_field,
                    "displayName" => $record_id_label,
                    "validation" => "",
                    "link" => $settings["tab_link"][$i] ?? "home",
                    "linkedEvent" => $settings["tab_field_link_event"][$i],
                    "linkedInstrument" => $settings["tab_field_link_instrument"][$i],
                ]
                # TODO add Label and Attempt
            ]);

            // Setup the tab's config
            foreach ($settings["tab_field"][$i] as $j => $field) {
                $name = $settings["tab_field_name"][$i][$j] ?? trim($this->getFieldLabel($field), ":?");
                $validation = $Proj->metadata[$field]["element_validation_type"] ?? "";
                $default = $settings["tab_field_default"][$i][$j] ?? "";
                $tabConfig[$tabOrder]["fields"][] = [
                    "field" => $field,
                    "map" => $this->getDictionaryValuesFor($field, $dd),
                    "displayName" => $name,
                    "validation" => $validation,
                    "isFormStatus" => $Proj->isFormStatus($field),
                    "fieldType" => $Proj->metadata[$field]["element_type"],
                    "default" => $default
                ];
                $allFields[] = $field;
            }
        }

        // Re-index to be sure we are zero based
        ksort($tabConfig);
        $tabConfig = array_combine(range(0, count($tabConfig) - 1), array_values($tabConfig));

        return [
            'config' => $tabConfig,
            'call2tabMap' => $call2TabMap,
            'tabNameMap' => $tabNameMap,
            'allFields' => $allFields
        ];
    }

    private function initGlobal()
    {
        $call_event = $this->getEventOfInstrument('call_log');
        $meta_event = $this->getEventOfInstrument('call_log_metadata');
        $data = array(
            "eventNameMap" => $this->getEventNameMap(),
            "prefix" => $this->getPrefix(),
            "user" => USERID,
            "userNameMap" => $this->getUserNameMap(),
            "static" => [
                "instrument" => $this->instrumentName,
                "instrumentLower" => $this->instrumentLower,
                "instrumentEvent" => $call_event,
                "record_id" => REDCap::getRecordIdField()
            ],
            "configError" => !($call_event && $meta_event),
            "router" => $this->getURL('router.php')
        );
        echo "<script>var " . $this->module_global . " = " . json_encode($data) . ";</script>";
    }

    public function getUserNameListConfig()
    {
        $config = [];
        $include = $this->getProjectSetting('username_include');
        $exclude = $this->getProjectSetting('username_exclude');
        foreach ($this->getProjectSetting('username_field') as $index => $field) {
            $config[$field] = [
                'include' => $include[$index],
                'exclude' => $exclude[$index]
            ];
        }
        return $config;
    }

    public function ajaxLog()
    {
        $sql = null;
        $event = null;
        REDCap::logEvent("Call Log", $_POST['details'], $sql, $_POST['record'], $event, $_GET['pid']);
    }

    public function loadReportConfig($excludeWithdrawn = true)
    {
        // Constants for data pull
        $project_id = $_GET['pid'];
        $metaEvent = $this->getEventOfInstrument('call_log_metadata');
        $report_fields = $this->getProjectSetting('report_field')[0];
        $report_names = $this->getProjectSetting('report_field_name')[0];
        $withdraw_config = [
            'event' => $this->getProjectSetting("withdraw_event"),
            'var' => $this->getProjectSetting("withdraw_var"),
            'tmp' => [
                'event' => $this->getProjectSetting("withdraw_tmp_event"),
                'var' => $this->getProjectSetting("withdraw_tmp_var")
            ]
        ];

        // Prep for getData
        $firstEvent = array_keys(REDCap::getEventNames())[0];
        $fields = array_merge([REDCap::getRecordIdField(), $this->metadataField, $withdraw_config['var'], $withdraw_config['tmp']['var']], $report_fields);
        $events = [$firstEvent, $metaEvent, $withdraw_config['event'], $withdraw_config['tmp']['event']];
        $records = null;
        $data = REDCap::getData($project_id, 'array', $records, $fields, $events);
        $result = [];

        // Pull the record Label
        $label = $this->getRecordLabel($project_id);

        // Loop to format data
        foreach ($data as $record => $record_data) {

            // Flatten
            $tmp = [];
            foreach ($record_data as $eventid => $event_data) {
                $tmp = array_merge(array_filter($event_data), $tmp ?? []);
            }

            // Check withdrawn
            $withdraw = !empty($tmp[$withdraw_config['var']]) || !empty($tmp[$withdraw_config['tmp']['var']]);
            if ($excludeWithdrawn && $withdraw) {
                continue;
            }

            // Load hardcoded data
            $result[$record]['_id'] = $record;
            if (!empty($tmp[$this->metadataField])) {
                $result[$record]['metadata'] = json_decode($tmp[$this->metadataField], true);
            }
            $result[$record]['_label'] = Piping::replaceVariablesInLabel($label, $record);

            // Load custom data
            foreach ($report_fields as $field) {
                $result[$record][$field] = $tmp[$field] ?? "";
            }
        }

        // Load custom cols
        foreach ($report_fields as $index => $field) {
            $result['_cols'][$field] = [
                'name' => $report_names[$index] ?? $this->getFieldLabel($field),
                'map' => $this->getDictionaryValuesFor($field)
            ];
        }
        return $result;
    }

    public function loadCallListData($skipDataPack = false)
    {
        $startTime = microtime(true);
        $project_id = $_GET['pid'];

        // Event IDs
        $callEvent = $this->getEventOfInstrument('call_log');
        $metaEvent = $this->getEventOfInstrument('call_log_metadata');

        // Withdraw Conditon Config
        $withdraw = [
            'event' => $this->getProjectSetting("withdraw_event"),
            'var' => $this->getProjectSetting("withdraw_var"),
            'tmp' => [
                'event' => $this->getProjectSetting("withdraw_tmp_event"),
                'var' => $this->getProjectSetting("withdraw_tmp_var")
            ]
        ];

        // MCV and Scheduled Vists Config for Live Data
        $autoRemoveConfig = $this->loadAutoRemoveConfig();
        $mcvDayOf = $this->getProjectSetting('show_mcv');

        // Large Configs
        $tabs = $this->loadTabConfig();
        $adhoc = $this->loadAdhocTemplateConfig();

        // Minor Prep
        $packagedCallData = [];
        $alwaysShowCallbackCol = false;
        $today = Date('Y-m-d');
        foreach ($tabs['config'] as $tab)
            $packagedCallData[$tab["tab_id"]] = [];

        // Construct the needed feilds (This is needed to save time. Loading all data takes a while)
        $fields = array_merge(
            [
                REDCap::getRecordIdField(), $this->metadataField, $withdraw['var'], $withdraw['tmp']['var'],
                'call_open_date', 'call_left_message', 'call_requested_callback', 'call_callback_requested_by', 'call_notes', 'call_open_datetime', 'call_open_user_full_name', 'call_attempt', 'call_template', 'call_event_name', 'call_callback_date', 'call_callback_time'
            ],
            array_values($autoRemoveConfig),
            $tabs['allFields']
        );

        // Main Loop
        $user_id = USERID ?? null;
        $records = $skipDataPack || empty($user_id) ? '-1' : null;
        $events = null;
        $group = REDCap::getUserRights($user_id)[$user_id]['group_id'];
        $dataLoad = REDCap::getData($project_id, 'array', $records, $fields, $events, $group);
        foreach ($dataLoad as $record => $recordData) {

            // Previously we checked for withdrawn status here, but end-users wanted
            // subjects to remain on the call list if they had a call back scheduled

            $meta = json_decode($recordData[$metaEvent][$this->metadataField], true);

            foreach ($meta as $callID => $call) {
                $fullCallID = $callID; // Full ID could be X|Y, X||Y or X|Y||Z. CALLID|EVENT||DATE
                [$callID, $part2, $part3] = array_pad(array_filter(explode('|', $callID)), 3, "");

                // Skip if call complete, debug call, or if call ID isn't assigned to a tab
                if ($call['complete'] || substr($callID, 0, 1) == '_' || empty($tabs['call2tabMap'][$callID]))
                    continue;

                // Skip when reminders, followups, adhocs aren't in window
                if (($call['template'] == 'reminder' || $call['template'] == 'followup') && !empty($call['start']) && ($call['start'] > $today))
                    continue;

                // Skip reminder calls day-of or future
                if (($call['template'] == 'reminder') && ($call['end'] <= $today))
                    continue;

                // Skip followups that are flagged for auto remove and are out of window (after the last day)
                if (($call['template'] == 'followup') && $autoRemoveConfig[$callID] && ($call['end'] < $today))
                    continue;

                // Skip New (onload) calls that have expire days
                if (($call['template'] == 'new') && $call['expire'] && (date('Y-m-d', strtotime($call['load'] . "+" . $call['expire'] . " days")) < $today))
                    continue;

                // Skip if MCV was created today (A call attempt was already made). Only use if config allows (mcvDayOf)
                if (($call['template'] == 'mcv') && (explode(' ', $call['appt'])[0] == $today) && !$mcvDayOf)
                    continue;

                // Gather Instance Level Data
                // This first line could be empty for New Entry calls, but it won't matter.
                $instanceData = $recordData['repeat_instances'][$callEvent][$this->instrumentLower][end($call['instances'])];
                $instanceEventData = $recordData[$call['event_id']];
                $instanceData = array_merge(
                    array_filter(empty($instanceEventData) ? [] : $instanceEventData, array($this, 'isNotBlank')),
                    array_filter($recordData[$callEvent], array($this, 'isNotBlank')),
                    array_filter(empty($instanceData) ? [] : $instanceData, array($this, 'isNotBlank'))
                );

                // Check to see if a call back was request for Today or Tomorrow+
                $instanceData['_callbackNotToday'] = ($instanceData['call_requested_callback'][1] == '1' && $instanceData['call_callback_date'] > $today);
                $instanceData['_callbackToday'] = ($instanceData['call_requested_callback'][1] == '1' && $instanceData['call_callback_date'] <= $today);

                // If no call back will happen today then check for autoremove and withdrawn conditions
                if (!$instanceData['_callbackToday']) {

                    // Skip MCV calls if past the autoremove date. Need Instance data for this
                    if (($call['template'] == 'mcv') && $autoRemoveConfig[$callID] && $instanceData[$autoRemoveConfig[$callID]] && !empty($instanceData[$autoRemoveConfig[$callID]]) && ($instanceData[$autoRemoveConfig[$callID]] < $today))
                        continue;

                    // Skip Scheduled Visit calls if past the autoremove date. Need Instance data for this
                    if (($call['template'] == 'visit') && $autoRemoveConfig[$callID] && $instanceData[$autoRemoveConfig[$callID]] && ($instanceData[$autoRemoveConfig[$callID]] < $today))
                        continue;

                    // Check if withdrawn or tmp withdrawn (Withdrawn until Tmp date)
                    // Checking here means that a scheduled call back on any call overrides our flag
                    if ($recordData[$withdraw['event']][$withdraw['var']])
                        continue;
                    if ($recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']] && $recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']] > $today)
                        continue;
                }

                // Set global if any Callback will be shown, done after our last check to skip a call
                $alwaysShowCallbackCol = $alwaysShowCallbackCol ? true : ($instanceData['call_requested_callback'][1] == '1' && $instanceData['call_callback_date'] <= $today);

                // Check if the call was recently opened
                $instanceData['_callStarted'] = strtotime($call['callStarted']) > strtotime('-' . $this->startedCallGrace . ' minutes');

                // Check if No Calls Today flag is set ( todo - remove the '==', we don't use strings here anymore )
                if ($today == $call['noCallsToday'] || in_array($today, $call['noCallsToday']))
                    $instanceData['_noCallsToday'] = true;

                // Save the no call history
                $instanceData['_noCallHistory'] = $call['noCallsToday'];

                // Check if we are at max call attempts for the day
                // While we are at it, assemble all of the note data too
                $attempts = $recordData[$callEvent]['call_open_date'] == $today ? 1 : 0;
                $instanceData['_callNotes'] = "";
                foreach (array_reverse($call['instances']) as $instance) {
                    $itterData = $recordData['repeat_instances'][$callEvent][$this->instrumentLower][$instance];
                    $leftMsg = $itterData['call_left_message'][1] == "1" ? '<b>Left Message</b>' : '';
                    $setCB = $itterData['call_requested_callback'][1] == "1" ? 'Set Callback' : '';
                    $text = $leftMsg && $setCB ? $leftMsg . " & " . $setCB : $leftMsg . $setCB . '&nbsp;';
                    $notes = $itterData['call_notes'] ? $itterData['call_notes'] : 'none';
                    $instanceData['_callNotes'] .= $itterData['call_open_datetime'] . '||' . $itterData['call_open_user_full_name'] . '||' . $text . '||' . $notes . '|||';
                    if ($itterData['call_open_date'] == $today)
                        $attempts++;
                }
                $instanceData['_atMaxAttempts'] = $call['hideAfterAttempt'] <= $attempts;
                $instanceData['call_attempt'] = count($call['instances']); // For displaying the number of past attempts on log

                // Add what the next instance should be for possible links
                $instanceData['_nextInstance'] = 1;
                if (!empty($recordData['repeat_instances'][$callEvent][$this->instrumentLower])) {
                    $instanceData['_nextInstance'] = end(array_keys($recordData['repeat_instances'][$callEvent][$this->instrumentLower])) + 1;
                } else if (!empty($recordData[$callEvent]['call_template'])) {
                    $instanceData['_nextInstance'] = 2;
                }

                // Add event_id for possible link to instruments
                $instanceData['_event'] = $call['event_id'];

                // Add the Event's name for possible display (only used by MCV?)
                $instanceData['call_event_name'] = $call['event'];

                // Add lower and upper windows (data is on reminders too but isn't displayed now)
                if ($call['template'] == 'followup') {
                    $instanceData['_windowLower'] = $call['start'];
                    $instanceData['_windowUpper'] = $call['end'];
                }

                // Not certain if we actualy need this. Need to investigate
                if ($call['template'] == 'mcv') {
                    $instanceData['_appt_dt'] = $call['appt'];
                }

                // Adhoc call time and reason
                if ($call['template'] == 'adhoc') {
                    $instanceData['_adhocReason'] = $adhoc[$callID]['reasons'][$call['reason']];
                    $instanceData['_adhocContactOn'] = $call['contactOn'];
                    $instanceData['_futureAdhoc'] = $call['start'] > $today;
                    $notes = $call['initNotes'] ?  $call['initNotes'] : "No Notes Taken";
                    if ($call['reporter'] != "")
                        $instanceData['_callNotes'] .= $call['reported'] . '||' . $call['reporter'] . '||' . '&nbsp;' . '||' . $notes . '|||';
                }

                // Make sure we 100% have a call ID (first attempt at a call won't get it from the normal data)
                $instanceData['_call_id'] = $fullCallID;

                // Pack data - done
                $packagedCallData[$tabs['call2tabMap'][$callID]][] = $instanceData;
            }
        }
        return array($packagedCallData, $tabs, $alwaysShowCallbackCol, round(((microtime(true) - $startTime)), 5));
    }
}
