<?php

namespace UWMadison\CallLog;

use ExternalModules\AbstractExternalModule;
use REDCap;
use Project;
use Design;
use Metadata;
use RestUtility;

require_once 'traits/BasicCallActions.php';
require_once 'traits/CallLogic.php';
require_once 'traits/Utility.php';
require_once 'traits/Configuration.php';

class CallLog extends AbstractExternalModule
{
    use BasicCallActions;
    use CallLogic;
    use Utility;
    use Configuration;

    public $tabsConfig;

    // Hard Coded Data Dictionary Values
    private $instrument = "call_log";
    private $instrumentMeta = "call_log_metadata";
    private $metadataField = "call_metadata";

    // Hard Coded Config
    private $startedCallGrace = '30'; # mins to assume that a call is ongoing for

    public function redcap_save_record($project_id, $record, $instrument)
    {
        $metadata = $this->getCallMetadata($project_id, $record);

        // Call Log Save
        if ($instrument == $this->instrument) {
            $this->reportDisconnectedPhone($project_id, $record);
            $this->metadataUpdateCommon($project_id, $record, $metadata);
            $this->saveCallMetadata($project_id, $record, $metadata);
            return;
        }

        // Call Metadata updates
        $config = $this->getCallTemplateConfig();
        $triggerForms = $this->getProjectSetting('trigger_save');
        $changes = [];
        if (empty($triggerForms) || in_array($instrument, $triggerForms)) {
            $a = $this->metadataFollowup($project_id, $record, $metadata, $config['followup']);
            $b = $this->metadataReminder($project_id, $record, $metadata, $config['reminder']);
            $c = $this->metadataMissedCancelled($project_id, $record, $metadata, $config['mcv']);
            $d = $this->metadataNeedToSchedule($project_id, $record, $metadata, $config['nts']);
            $changes = [$a, $b, $c, $d];
        }
        $e = $this->metadataNewEntry($project_id, $record, $metadata, $config['new']);
        $f = $this->metadataPhoneVisit($project_id, $record, $metadata, $config['visit']);
        if (in_array(true, array_merge($changes, [$e, $f]))) {
            $this->saveCallMetadata($project_id, $record, $metadata);
        }

        // Misc Call Updates (Could still touch metadata, but returns junk, saves own data)
        $this->callStarted($project_id, $record, $metadata);
        $this->updateDisconnectedPhone($project_id, $record);
    }

    public function redcap_every_page_top($project_id)
    {
        if (!defined("USERID")) return;

        $this->initGlobal();
        include('templates.php');
        $this->includeJs('js/templates.js');

        // EM Pages
        if ($this->isPage('ExternalModules/') && $_GET['prefix'] == 'call_log') {

            // Metadata Reports 
            if (isset($_GET['metaReport'])) {
                $this->includeCss('css/reports.css');
                $this->includeJs('js/reports.js', 'defer');
                $this->passArgument('reportConfig', $this->getReportConfig());
                return;
            }

            // Call List
            $this->includeCss('css/list.css');
            $this->includeJs('js/call_list.js', 'defer', 'module');
            $this->tabsConfig = $this->getTabConfig();
            $this->passArgument('tabs', $this->tabsConfig);
            $this->passArgument('usernameLists', $this->getUserNameListConfig());
        }

        // Record Home Page
        else if ($this->isPage('DataEntry/record_home.php') && $_GET['id']) {
            $this->includeJs('js/record_home_page.js', 'defer');
        }

        // Custom Config page
        else if ($this->isPage('ExternalModules/manager/project.php') && $project_id) {
            $this->includeCss('css/config.css');
            $this->includeJs('js/config.js', 'defer');
        }
    }

    public function redcap_data_entry_form($project_id, $record, $instrument)
    {
        $summary = $this->getProjectSetting('call_summary');
        $this->passArgument('recentCaller', $this->recentCallStarted($project_id, $record));
        $this->includeJs('js/data_entry.js', 'defer');

        // Call Log only info
        if ($instrument == $this->instrument) {
            $this->passArgument('adhoc', $this->getAdhocTemplateConfig());
            $this->includeJs('js/call_log.js', 'defer');
        }

        // Call Log + anything w/ summary table on it
        if (in_array($instrument, array_merge($summary, [$this->instrument]))) {
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

    public function redcap_module_ajax($action, $payload, $project_id, $record)
    {
        $callListData = false;
        $success = true;
        $result = [];
        $record = $record ?? $payload['record'];
        $metadata = $this->getCallMetadata($project_id, $record);
        $config = $this->getCallTemplateConfig();

        switch ($action) {
            case "log":
                $this->projectLog($payload['text'], $record, $payload['event'], $project_id);
                break;
            case "getData":
                # Load for the Call List
                $callListData = $this->getCallListData($project_id);
                break;
            case "deployInstruments":
                $success = $this->deployInstruments($project_id);
                break;
            case "newAdhoc":
                # Posted to by the call log to save a new adhoc call
                if (empty($payload['id'])) break;
                $result = $this->metadataAdhoc($project_id, $record, $config["adhoc"], [
                    'id' => $payload['id'],
                    'date' => $payload['date'],
                    'time' => $payload['time'],
                    'reason' => $payload['reason'],
                    'notes' => $payload['notes'],
                    'reporter' => $payload['reporter']
                ]);
                break;
            case "callDelete":
                # Delete instance of log from the summary table on call log
                $this->deleteLastCallInstance($project_id, $record, $metadata);
                break;
            case "metadataSave":
                # Posted to by the call log to save the record's data via the console.
                # This is useful for debugging and resolving enduser issues.
                if (empty($payload['metadata'])) break;
                $result = $this->saveCallMetadata($project_id, $record, json_decode($payload['metadata'], true));
                break;
            case "setCallStarted":
                # This page is posted to by the call list to flag a call as in progress
                if (empty($payload['id']) || empty($payload['user']) || empty($metadata)) break;
                $result = $this->callStarted($project_id, $record, $metadata, $payload['id'], $payload['user']);
                break;
            case "setCallEnded":
                # This page is posted to by the call list to flag a call as no longer in progress
                if (empty($payload['id']) || empty($metadata)) break;
                $result = $this->callEnded($project_id, $record, $metadata, $payload['id']);
                break;
            case "setNoCallsToday":
                # This page is posted to by the call list to flag a call as "no calls today"
                if (empty($payload['id'])) break;
                $result = $this->noCallsToday($project_id, $record, $metadata, $payload['id']);
                break;
        }

        return array_merge([
            "action" => $action,
            "success" => $success,
            "data" => $callListData
        ], $result);
    }

    public function api()
    {
        // POST /redcap/api/?type=module&prefix=call_log&page=api&NOAUTH

        $success = true;
        $result = [];
        $request = RestUtility::processRequest();
        $payload = $request->getRequestVars();
        $project_id = $payload['projectid'];
        $action = $payload['action'];
        $records = json_decode($payload['record_list'] ?? '');
        $records = $records ?? [$payload['record']];

        global $Proj;
        $Proj = new Project($project_id);

        $config = $this->getCallTemplateConfig();
        switch ($action) {
            case "newAdhoc":
                # Identical to native but via API
                # Paylod: (req) type, record, (opt) date, time, reason, reporter. All Strings
                if (empty($payload['type'])) {
                    $success = false;
                    break;
                }
                foreach ($records as $record) {
                    $result[] = $this->metadataAdhoc($project_id, $record, $config["adhoc"], [
                        'id' => $payload['type'],
                        'date' => $payload['date'],
                        'time' => $payload['time'],
                        'reason' => $payload['reason'],
                        'reporter' => $payload['reporter']
                    ]);
                }
                break;
            case "resolveAdhoc":
                # Intended to be posted to by an outside script or DET to resolve an existing adhoc call on a record(s)
                # Paylod: code - String, record_list - Json Array
                if (empty($payload['code'])) {
                    $success = false;
                    break;
                }
                foreach ($records as $record) {
                    $record = trim($record);
                    $metadata = $this->getCallMetadata($project_id, $record);
                    $this->resolveAdhoc($project_id, $record, $payload['code'], $metadata);
                }
                break;
            case "newEntry":
                # This page is intended to be posted to by an outside script to load New Entry calls for any number of records
                # Paylod: record_list - Json Array
                foreach ($records as $record) {
                    $metadata = $this->getCallMetadata($project_id, $record);
                    $changes = $this->metadataNewEntry($project_id, $record, $metadata, $config['new']);
                    if ($changes) {
                        $result[] = $this->saveCallMetadata($project_id, $record, $metadata);
                    }
                }
                break;
            case "schedule":
                # This page is intended to be posted to by an outside script after scheduling occurs. 
                # Paylod: record_list - Json Array
                foreach ($records as $record) {
                    $record = trim($record);
                    $metadata = $this->getCallMetadata($project_id, $record);
                    $a = $this->metadataReminder($project_id, $record, $metadata, $config['reminder']);
                    $b = $this->metadataMissedCancelled($project_id, $record, $metadata, $config['mcv']);
                    $c = $this->metadataNeedToSchedule($project_id, $record, $metadata, $config['nts']);
                    if (in_array(true, [$a, $b, $c])) {
                        $result[] = $this->saveCallMetadata($project_id, $record, $metadata);
                    }
                }
                break;
        }
        return json_encode([
            "action" => $action,
            "success" => $success,
            "result" => $result
        ]);
    }

    public function cronFollowUp($cronInfo)
    {
        global $Proj;
        $today = Date('Y-m-d');
        if (!defined("PROJECT_ID"))
            define("PROJECT_ID", 1); // Not used, but getEventNames needs it set.

        // foreach ($this->getProjectsWithModuleEnabled() as $project_id) {
        //     $_GET['pid'] = $project_id;
        //     $Proj = new Project($project_id);
        //     $project_record_id = $this->getRecordIdField($project_id);

        //     $config = $this->getCallTemplateConfig()["followup"];
        //     if (empty($config)) continue;
        //     foreach ($config as $callConfig) {

        //         $fields = [$project_record_id, $callConfig['field'], $callConfig['followup_end']];
        //         $project_data = REDCap::getData($project_id, 'array', null, $fields);

        //         foreach ($project_data as $record => $data) {

        //             $meta = $this->getCallMetadata($project_id, $record);
        //             $event = $callConfig['event'];

        //             // Call ID already exists
        //             if ($meta[$callConfig["id"]])
        //                 continue;

        //             // Calc start day
        //             $start = $data[$event][$callConfig["field"]];
        //             if (empty($start))
        //                 continue;
        //             if ($callConfig["days"])
        //                 $start = date('Y-m-d', strtotime($start . ' + ' . $callConfig["days"] . ' days'));

        //             // Calc end day or use a default
        //             $end = $data[$event][$callConfig["end"]];
        //             if ($callConfig['length'])
        //                 $end = date('Y-m-d', strtotime($start . ' + ' . $callConfig["length"] . ' days'));

        //             if (($today >= $start) && ($today <= $end)) {
        //                 $meta[$callConfig['id']] = [
        //                     "start" => $start,
        //                     "end" => $end,
        //                     "template" => 'followup',
        //                     "event_id" => $callConfig['event'],
        //                     "name" => $callConfig['name'],
        //                     "instances" => [],
        //                     "voiceMails" => 0,
        //                     "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
        //                     "complete" => false
        //                 ];
        //                 $this->saveCallMetadata($project_id, $record, $meta);
        //                 $this->projectLog("Need to Schedue call {$callConfig['id']} created during cron", $record, $event, $project_id);
        //             }
        //         }
        //     }
        // }
        return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
    }

    public function cronNeedToSchedule($cronInfo)
    {
        global $Proj;
        global $longitudinal;
        $longitudinal = True;
        $today = Date('Y-m-d');
        if (!defined("PROJECT_ID"))
            define("PROJECT_ID", 1); // Not used, but getEventNames needs it set.

        foreach ($this->getProjectsWithModuleEnabled() as $project_id) {
            $_GET['pid'] = $project_id;
            $Proj = new Project($project_id);
            $project_record_id = $this->getRecordIdField($project_id);

            $config = $this->getCallTemplateConfig()["nts"];
            if (empty($config)) continue;
            foreach ($config as $callConfig) {

                $fields = [$project_record_id, $callConfig['apptDate'], $callConfig['indicator'], $callConfig['skip'], $callConfig["window"]];
                $project_data = REDCap::getData($project_id, 'array', null, $fields);

                foreach ($project_data as $record => $data) {

                    $meta = $this->getCallMetadata($project_id, $record);
                    $event = $callConfig['event'];
                    $days = $callConfig['windowDaysBefore'];
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
                    // Make sure a window exists
                    if (empty($callConfig["window"]))
                        continue;
                    // Make sure data is in that window
                    if (empty($data[$event][$callConfig["window"]]))
                        continue;

                    if ($data[$event][$callConfig["window"]] <= date('Y-m-d', strtotime("$today - $days days"))) {
                        $meta[$callConfig['id']] = [
                            "template" => 'nts',
                            "event_id" => $event,
                            "name" => $callConfig['name'],
                            "instances" => [],
                            "voiceMails" => 0,
                            "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                            "complete" => false
                        ];
                        $this->saveCallMetadata($project_id, $record, $meta);
                        $this->projectLog("Need to Schedue call {$callConfig['id']} created during cron", $record, $event, $project_id);
                    }
                }
            }
        }
        return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
    }

    private function deployInstruments($project_id)
    {
        // Check if deployed
        $instruments = array_keys(REDCap::getInstrumentNames(null, $project_id));
        if (in_array($this->instrument, $instruments)) return false;
        if (in_array($this->instrumentMeta, $instruments)) return false;

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
        $errors = count($sql_errors) > 0;
        db_query($errors ? "ROLLBACK" : "COMMIT");
        db_query("SET AUTOCOMMIT=1");
        return !$errors;
    }

    private function metadataAdhoc($project_id, $record, $config, $payload)
    {
        $config = array_filter($config, function ($x) use ($payload) {
            return $x['id'] == $payload['id'];
        });
        $config = end($config);
        $metadata = $this->getCallMetadata($project_id, $record);
        if (empty($payload['date']))
            $payload['date'] = Date('Y-m-d');
        $reported = Date('Y-m-d H:i:s');
        $reporter = $this->getUserNameMap($project_id)[$payload['reporter']] ?? $payload['reporter'];
        $metadata[$config['id'] . '||' . $reported] = [
            "start" => $payload['date'],
            "contactOn" => trim($payload['date'] . " " . $payload['time']),
            "reported" => $reported,
            "reporter" => $reporter,
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

    private function resolveAdhoc($project_id, $record, $code, $metadata)
    {
        foreach ($metadata as $callID => $callData) {
            if ($callData['complete'] || $callData['reason'] != $code) continue;
            $callData['complete'] = true;
            $callData['completedBy'] = "REDCap";
            $this->projectLog("Adhoc call {$callID} marked as complete via API.", $record, null, $project_id);
        }
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }

    private function deleteLastCallInstance($project_id, $record, $metadata)
    {
        $event = $this->getEventOfInstrument($this->instrument);
        $data = REDCap::getData($project_id, 'array', $record, null, $event);
        $instance = end(array_keys($data[$record]['repeat_instances'][$event][$this->instrument]));
        $instance = $instance ? $instance : '1';
        $instanceText = $instance != '1' ? ' AND instance= ' . $instance : ' AND isnull(instance)';
        foreach ($metadata as $index => $call) {
            $tmp = $metadata[$index]['instances'];
            $metadata[$index]['instances'] = array_values(array_diff($call['instances'], [$instance]));
            // If we did remove a call then make sure we mark the call as incomplete.
            // We currently don't allow completed calls to actually be deleted though as there could be unforeseen issues.
            if ((count($tmp) > 0) && (count($tmp) != count($metadata[$index]['instances'])))
                $metadata[$index]['complete'] = false;
        }
        $fields = array_values(array_intersect(REDCap::getFieldNames($this->instrument), array_keys($data[$record][$event])));
        $table = REDCap::getDataTable($project_id);
        db_query('DELETE FROM ' . $table . ' WHERE project_id=' . $project_id . ' AND record=' . $record . $instanceText . ' AND (field_name="' . implode('" OR field_name="', $fields) . '");');
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }

    private function getAllCallData($project_id, $record)
    {
        $event = $this->getEventOfInstrument($this->instrument);
        $data = REDCap::getData($project_id, 'array', $record, null, $event);
        $callData = $data[$record]['repeat_instances'][$event][$this->instrument];
        return empty($callData) ? [1 => $data[$record][$event]] : $callData;
    }

    private function getCallMetadata($project_id, $record)
    {
        $metadata = REDCap::getData($project_id, 'array', $record, $this->metadataField)[$record][$this->getEventOfInstrument($this->instrumentMeta)][$this->metadataField];
        return empty($metadata) ? [] : json_decode($metadata, true);
    }

    private function saveCallMetadata($project_id, $record, $data)
    {
        $table = REDCap::getDataTable($project_id);
        $sql = "SELECT field_name FROM $table WHERE project_id = ? and record = ? LIMIT 1";
        $result = $this->query($sql, [$project_id, $record]);
        if (empty($result->fetch_assoc())) return false;
        return REDCap::saveData(
            $project_id,
            'array',
            [
                $record => [
                    $this->getEventOfInstrument($this->instrumentMeta) => [
                        $this->metadataField => json_encode($data)
                    ]
                ]
            ]
        );
    }

    private function recentCallStarted($project_id, $record)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (empty($meta)) return '';
        $grace = $this->startedCallGrace; // Minutes of Grace time
        $user = $this->getUser()->getUsername();
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

    private function getCallListData($project_id)
    {
        // Event IDs, Configs etc
        $callEvent = $this->getEventOfInstrument($this->instrument);
        $metaEvent = $this->getEventOfInstrument($this->instrumentMeta);
        $withdraw = $this->getWithdrawConfig();
        $autoRemoveConfig = $this->getAutoRemoveConfig();
        $dayOf = $this->getProjectSetting('same_day_mcv_nts');
        $tabs = $this->getTabConfig();
        $adhoc = $this->getAdhocTemplateConfig();
        $eventNameToId = array_flip(REDCap::getEventNames());

        // Minor Prep
        $packagedCallData = [];
        $alwaysShowCallbackCol = false;
        $today = Date('Y-m-d');
        foreach ($tabs['config'] as $tab)
            $packagedCallData[$tab["tab_id"]] = [];

        // Construct the needed feilds (This is needed to save time. Loading all data takes a while)
        $fields = array_merge(
            [
                REDCap::getRecordIdField(),
                $this->metadataField,
                $withdraw['var'],
                $withdraw['tmp']['var'],
                'call_open_date',
                'call_left_message',
                'call_requested_callback',
                'call_callback_requested_by',
                'call_notes',
                'call_open_datetime',
                'call_open_user_full_name',
                'call_attempt',
                'call_template',
                'call_event_name',
                'call_callback_date',
                'call_callback_time'
            ],
            array_values($autoRemoveConfig),
            $tabs['allFields']
        );

        // Main Loop
        $user_id = USERID ?? null;
        $records = empty($user_id) ? '-1' : null;
        $events = null;
        $group = REDCap::getUserRights($user_id)[$user_id]['group_id'];
        $dataLoad = REDCap::getData($project_id, 'array', $records, $fields, $events, $group);
        foreach ($dataLoad as $record => $recordData) {

            // Previously we checked for withdrawn status here, but end-users wanted
            // subjects to remain on the call list if they had a call back scheduled

            $meta = json_decode($recordData[$metaEvent][$this->metadataField], true);

            foreach ($meta as $callID => $call) {
                $fullCallID = $callID; // Full ID could be X|Y, X||Y or X|Y||Z. CALLID|EVENT||DATE
                [$callID, $callID_event, $part3] = array_pad(array_filter(explode('|', $callID)), 3, "");

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

                // Skip if MCV was sch for today (A call attempt was already made). Only use if config allows
                if (($call['template'] == 'mcv') && (explode(' ', $call['appt'])[0] == $today) && !$dayOf)
                    continue;

                // Skip if NTS was created today (A call attempt was already made). Only use if config allows
                if (($call['template'] == 'nts') && ($call['created'] == $today) && !$dayOf)
                    continue;

                // Gather Instance Level Data
                // This first line could be empty for New Entry calls, but it won't matter.
                $instanceData = $recordData['repeat_instances'][$callEvent][$this->instrument][end($call['instances'])];
                $instanceEventData = $recordData[$call['event_id']];
                $instanceData = array_merge(
                    array_filter(empty($instanceEventData) ? [] : $instanceEventData, [$this, 'isNotBlank']),
                    array_filter($recordData[$callEvent], [$this, 'isNotBlank']),
                    array_filter(empty($instanceData) ? [] : $instanceData, [$this, 'isNotBlank'])
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

                // Check if No Calls Today flag is set
                if (!is_array($call['noCallsToday'])) // Old use of noCallsToday was as a string
                    $call['noCallsToday'] = [$call['noCallsToday']];
                if (in_array($today, $call['noCallsToday']))
                    $instanceData['_noCallsToday'] = true;

                // Save the no call history
                $instanceData['_noCallHistory'][] = $call['noCallsToday'];

                // Check if we are at max call attempts for the day
                // While we are at it, assemble all of the note data too
                $attempts = $recordData[$callEvent]['call_open_date'] == $today ? 1 : 0;
                $instanceData['_callNotes'] = "";
                foreach (array_reverse($call['instances']) as $instance) {
                    $itterData = $recordData['repeat_instances'][$callEvent][$this->instrument][$instance];
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
                if (!empty($recordData['repeat_instances'][$callEvent][$this->instrument])) {
                    $instanceData['_nextInstance'] = end(array_keys($recordData['repeat_instances'][$callEvent][$this->instrument])) + 1;
                } else if (!empty($recordData[$callEvent]['call_template'])) {
                    $instanceData['_nextInstance'] = 2;
                }

                // Add event_id for possible link to instruments
                $instanceData['_event'] = $eventNameToId[$callID_event];

                // Add the Event's name for possible display (only used by MCV?)
                $instanceData['call_event_name'] = $callID_event;

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
        return [
            "data" => $packagedCallData,
            "showCallback" => $alwaysShowCallbackCol
        ];
    }
}
