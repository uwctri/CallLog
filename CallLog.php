<?php

namespace UWMadison\CallLog;

use ExternalModules\AbstractExternalModule;
use REDCap;
use User;
use Project;

class CallLog extends AbstractExternalModule
{
    private $module_global = 'CallLog';

    // Hard Coded Data Dictionary Values
    public $instrumentName = "Call Log";
    public $instrumentLower = "call_log";
    public $instrumentMeta = "call_log_metadata";
    public $metadataField = "call_metadata";

    // Cache for functions
    private $_dataDictionary = [];
    private $_callTemplateConfig = [];
    private $_callData = [];

    // Hard Coded Config
    public $startedCallGrace = '30';

    /////////////////////////////////////////////////
    // REDCap Hooks
    /////////////////////////////////////////////////

    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $hash, $response, $instance)
    {
        if ($instrument == $this->instrumentLower) {
            // Update any metadata info that needs it
            $this->reportDisconnectedPhone($project_id, $record);
            $this->metadataUpdateCommon($project_id, $record);
        } else {
            $triggerForm = $this->getProjectSetting('trigger_save');
            if (empty($triggerForm) || ($instrument == $triggerForm)) {
                // Check if we need to set (or remove) a new Follow up call (Checkout visit completed)
                $this->metadataFollowup($project_id, $record);
                // Check if we have changed how reminders should be sent
                $this->metadataReminder($project_id, $record);
                // Check if we need to set a new Missed/Cancelled call (Checkout issue reported)
                $this->metadataMissedCancelled($project_id, $record);
                // Check if we need to set a new Need to Schedule call (Checkout vist completed)
                $this->metadataNeedToSchedule($project_id, $record);
            }
            // Check if the Phone issues are resolved
            $this->updateDisconnectedPhone($project_id, $record);
            // Check if we are a New Entry or not
            $this->metadataNewEntry($project_id, $record);
            // Check if we need to make a Schedueld Phone Visit call log (Check in was started)
            $this->metadataPhoneVisit($project_id, $record);
            // Check if we need to extend the duration of the call flag
            $this->metadataCallStartedUpdate($project_id, $record);
        }
    }

    public function redcap_every_page_top($project_id)
    {
        if (!defined("USERID")) //Skip if user isn't logged in.
            return;

        include('templates.php');
        $this->initGlobal();
        $this->includeJs('js/every_page.js');

        // Record Home Page
        if (PAGE == 'DataEntry/record_home.php' && $_GET['id']) {
            $this->includeJs('js/record_home_page.js');
        }

        // Custom Config page
        if (strpos(PAGE, 'manager/project.php') !== false && $project_id != NULL) {
            $this->includeCss('css/config.css');
            $this->includeJs('js/config.js');
        }

        // Index of Call List
        if (strpos(PAGE, 'ExternalModules/index.php') !== false && $project_id != NULL) {
            $this->includeJs('js/cookie.min.js');
            $this->includeCss('css/list.css');
            $this->includeJs('js/call_list.js');
            $this->passArgument('usernameLists', $this->getUserNameListConfig());
            $this->passArgument('eventNameMap', $this->getEventNameMap());
        }
    }

    public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        $summary = $this->getProjectSetting('call_summary');
        if ($instrument == $this->instrumentLower) {
            $this->passArgument('metadata', $this->getCallMetadata($project_id, $record));
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->passArgument('eventNameMap', $this->getEventNameMap());
            $this->passArgument('adhoc', $this->loadAdhocTemplateConfig());
            $this->includeCss('css/log.css');
            $this->includeJs('js/summary_table.js');
            $this->includeJs('js/call_log.js');
        } elseif (in_array($instrument, $summary)) {
            $this->passArgument('metadata', $this->getCallMetadata($project_id, $record));
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->includeCss('css/log.css');
            $this->includeJs('js/summary_table.js');
        }
        $this->passArgument('recentCaller', $this->recentCallStarted($project_id, $record));
    }

    public function redcap_module_link_check_display($project_id, $link)
    {
        return (strpos($link['url'], 'index') !== false) ? true : null;
    }

    /////////////////////////////////////////////////
    // Issue Reporting
    /////////////////////////////////////////////////

    public function reportDisconnectedPhone($project_id, $record)
    {
        $config = $this->loadBadPhoneConfig();
        if ($config['_missing'])
            return;
        $event = $config['event'];
        $oldNotes = REDCap::getData($project_id, 'array', $record, $config['notes'], $event)[$record][$event][$config['notes']];
        $callData = end($this->getAllCallData($project_id, $record));
        if ($callData['call_disconnected'][1] == "1") {
            $write[$record][$event][$config['flag']] = "1";
            $write[$record][$event][$config['notes']] = $callData['call_open_date'] . ' ' . $callData['call_open_time'] . ' ' . $callData['call_open_user_full_name'] . ': ' . $callData['call_notes'] . "\r\n\r\n" . $oldNotes;
            REDCap::saveData($project_id, 'array', $write, 'overwrite');
        }
    }

    public function updateDisconnectedPhone($project_id, $record)
    {
        $config = $this->loadBadPhoneConfig();
        if ($config['_missing'])
            return;
        $event = $config['event'];
        $isResolved = REDCap::getData(
            $project_id,
            'array',
            $record,
            $config['resolved'],
            $event
        )[$record][$event][$config['resolved']][1] == "1";
        if ($isResolved) {
            $write[$record][$event][$config['flag']] = "";
            $write[$record][$event][$config['notes']] = "";
            $write[$record][$event][$config['resolved']][1] = "0";
            REDCap::saveData($project_id, 'array', $write, 'overwrite');
        }
    }

    /////////////////////////////////////////////////
    // Metadata Updating / Creation
    /////////////////////////////////////////////////

    public function metadataNewEntry($project_id, $record)
    {
        // Also envoked via URL post for bulk load scripts
        $meta = $this->getCallMetadata($project_id, $record);
        if (!empty($meta))
            return;
        $config = $this->loadCallTemplateConfig()["new"];
        foreach ($config as $callConfig) {
            // Don't re-create call
            if (!empty($meta[$callConfig['id']]))
                continue;
            $meta[$callConfig['id']] = [
                "template" => 'new',
                "event" => '', //None for new entry calls
                "event_id" => '',
                "name" => $callConfig['name'],
                "load" => date("Y-m-d H:i"),
                "instances" => [],
                "voiceMails" => 0,
                "expire" => $callConfig['expire'],
                "maxVoiceMails" => $callConfig['maxVoiceMails'],
                "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                "complete" => false
            ];
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataFollowup($project_id, $record)
    {
        $config = $this->loadCallTemplateConfig()["followup"];
        $disableRounding = $this->getProjectSetting('disable_rounding');
        if (empty($config))
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach ($config as $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['field'],$callConfig['end']])[$record];
            if (!empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == "") {
                //Anchor appt was removed, get rid of followup call too.
                unset($meta[$callConfig['id']]);
            } elseif (empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "") {
                // Anchor is set and the meta doesn't have the call id in it yet
                $start = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $callConfig['days'] . ' days'));
                $end = $data[$callConfig['event']][$callConfig['end']];
                if (empty($end)) {
                    $end = $callConfig['days'] + $callConfig['length'];
                    $end = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $end . ' days'));
                }
                $meta[$callConfig['id']] = [
                    "start" => $disableRounding ? $start : $this->roundDate($start, 'down'),
                    "end" => $disableRounding ? $end : $this->roundDate($end, 'up'),
                    "template" => 'followup',
                    "event_id" => $callConfig['event'],
                    "event" => $eventMap[$callConfig['event']],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
                    "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "autoRemove" => $callConfig['autoRemove'],
                    "complete" => false
                ];
            } elseif (!empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "") {
                // Update the start/end dates if the call exists and the anchor isn't blank 
                $start = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $callConfig['days'] . ' days'));
                $start = $disableRounding ? $start : $this->roundDate($start, 'down');
                $end = $data[$callConfig['event']][$callConfig['end']];
                if (empty($end)) {
                    $end = $callConfig['days'] + $callConfig['length'];
                    $end = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $end . ' days'));
                }
                $end = $disableRounding ? $end : $this->roundDate($end, 'up');
                if (($meta[$callConfig['id']]['start'] != $start) || ($meta[$callConfig['id']]['end'] != $end)) {
                    $meta[$callConfig['id']]['start'] = $start;
                    $meta[$callConfig['id']]['end'] = $end;
                }
            }
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataReminder($project_id, $record)
    {
        // Can be envoked via URL Post from custom Scheduling solution
        $config = $this->loadCallTemplateConfig()["reminder"];
        if (empty($config))
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        $today = date('Y-m-d');
        foreach ($config as $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['field'], $callConfig['removeVar']])[$record];
            if (
                !empty($meta[$callConfig['id']]) && count($meta[$callConfig['id']]['instances']) == 0 &&
                $data[$callConfig['removeEvent']][$callConfig['removeVar']]
            ) {
                // Alt flag was set and we haven't recorded calls. Delete the metadata
                unset($meta[$callConfig['id']]);
                continue;
            }
            if ($data[$callConfig['removeEvent']][$callConfig['removeVar']])
                continue;
            $newStart = $this->dateMath($data[$callConfig['event']][$callConfig['field']], '-', $callConfig['days']);
            $newEnd = $this->dateMath($data[$callConfig['event']][$callConfig['field']], '+', $callConfig['days'] == 0 ? 365 : 0);
            if (
                !empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == ""
                && count($meta[$callConfig['id']]['instances']) == 0
            ) {
                // Scheduled appt was removed and no call was made, get rid of reminder call too.
                unset($meta[$callConfig['id']]);
            } elseif (!empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == "") {
                // Scheduled appt was removed, but a call was made, mark the reminder as complete
                $meta[$callConfig['id']]["complete"] = true;
                $meta[$callConfig['id']]["completedBy"] = "REDCap";
                $this->projectLog("Reminder call {$callConfig['id']} marked as complete, appointment was removed.");
            } elseif (!empty($meta[$callConfig['id']]) && ($data[$callConfig['event']][$callConfig['field']] <= $today)) {
                // Appt is today, autocomplete the call so it stops showing up places, we might double set but it doesn't matter
                $meta[$callConfig['id']]['complete'] = true;
                $meta[$callConfig['id']]["completedBy"] = "REDCap";
                $this->projectLog("Reminder call {$callConfig['id']} marked as complete, appointment is today.");
            } elseif (
                !empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "" &&
                ($meta[$callConfig['id']]['start'] != $newStart || $meta[$callConfig['id']]['end'] != $newEnd)
            ) {
                // Scheduled appt exists, the meta has the call id, but the dates don't match (re-shchedule occured)
                $meta[$callConfig['id']]['complete'] = false;
                $meta[$callConfig['id']]['start'] = $newStart;
                $meta[$callConfig['id']]['end'] = $newEnd;
                $this->projectLog("Reminder call {$callConfig['id']} marked as incomplete, appointment was rescheduled.");
            } elseif (empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "") {
                // Scheduled appt exists and the meta doesn't have the call id in it yet
                $meta[$callConfig['id']] = [
                    "start" => $newStart,
                    "end" => $newEnd,
                    "template" => 'reminder',
                    "event_id" => $callConfig['event'],
                    "event" => $eventMap[$callConfig['event']],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
                    "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
            }
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataMissedCancelled($project_id, $record)
    {
        $config = $this->loadCallTemplateConfig()["mcv"];
        if (empty($config))
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach ($config as $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator']])[$record][$callConfig['event']];
            $idExact = $callConfig['id'] . '||' . $data[$callConfig['apptDate']];
            if (empty($meta[$idExact]) && !empty($data[$callConfig['apptDate']]) && !empty($data[$callConfig['indicator']])) {
                // Appt is set, Indicator is set, and metadata is missing, write it.
                $meta[$idExact] = [
                    "appt" => $data[$callConfig['apptDate']],
                    "template" => 'mcv',
                    "event_id" => $callConfig['event'],
                    "event" => $eventMap[$callConfig['event']],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    #"autoRemove" => !empty($autoRemoveField),        No longer recorderd on metadata
                    #"autoRemoveField" => $callConfig['autoRemove'],
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
                    "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
            } elseif (!empty($meta[$idExact]) && !empty($data[$callConfig['apptDate']]) && empty($data[$callConfig['indicator']])) {
                // The visit has been reschedueld for the exact previous time, or maybe user error
                // Previously we would usent those calls with 0 instances, but this leads to an issue if a mcv is reschedueld on the first try
                $meta[$idExact]['complete'] = true;
                $meta[$idExact]["completedBy"] = "REDCap";
                $this->projectLog("Missed/Cancelled call {$idExact} marked as complete, appointment was rescheduled.");
            }

            // Search for similar IDs and complete/remove them. We should only have 1 MCV call per event active on the call log
            foreach ($meta as $callID => $callData) {
                if ($callID == $idExact || $callData['complete'] || $callData['template'] != "mcv" || $callData['event'] != $callConfig['event'])
                    continue;
                if (count($callData["instances"]) == 0)
                    unset($meta[$callID]);
                else {
                    $callData['complete'] = true;
                    $callData['completedBy'] = "REDCap";
                    $this->projectLog("Missed/Cancelled call {$callID} marked as complete, call appears to be a duplicate.");
                }
            }
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    function getRecordIdField($project_id)
    {
        $sql = "SELECT field_name FROM redcap_metadata WHERE field_order = 1 AND project_id = ?";
        $result = $this->query($sql, [$project_id]);
        $row = $result->fetch_assoc();
        return $row["field_name"];
    }

    function cronNeedToSchedule($cronInfo)
    {
        global $Proj;
        $originalPid = $_GET['pid'];
        $today = Date('Y-m-d');

        foreach ($this->getProjectsWithModuleEnabled() as $localProjectId) {
            $_GET['pid'] = $localProjectId;
            $project_id = $localProjectId;
            define("PROJECT_ID", $project_id);
            $Proj = new Project($project_id);
            $project_record_id = $this->getRecordIdField($project_id);
            $eventMap = REDCap::getEventNames(true);

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
                            "event_id" => $callConfig['event'],
                            "event" => $eventMap[$callConfig['event']],
                            "name" => $callConfig['name'],
                            "instances" => [],
                            "voiceMails" => 0,
                            "maxVoiceMails" => $callConfig['maxVoiceMails'],
                            "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                            "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                            "complete" => false
                        ];
                        $this->projectLog("Need to Schedue call {$callConfig['id']} created during cron");
                    }
                }
            }
        }

        $_GET['pid'] = $originalPid;
        return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
    }

    public function metadataNeedToSchedule($project_id, $record)
    {
        $config = $this->loadCallTemplateConfig()["nts"];
        if (empty($config))
            return;
        global $Proj;
        $orderedEvents = array_combine(array_map(function ($x) {
            return $x['day_offset'];
        }, $Proj->eventInfo), array_keys($Proj->eventInfo));
        $callLogEvent = $this->getProjectSetting('call_log_event');
        $metadataEvent = $this->getProjectSetting('metadata_event');
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach ($config as $i => $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator'], $callConfig['skip']])[$record];
            $prevEvent = $orderedEvents[array_search($callConfig['event'], $orderedEvents) - 1];
            // If previous indicator is set (i.e. it was attended) and current event's appt_date is blank, and its not attended then set need to schedule. Also check that skip is either not configured or that it is not-truthy (i.e. 0 or empty).
            if (
                empty($meta[$callConfig['id']]) && !empty($data[$prevEvent][$callConfig['indicator']]) && empty($data[$callConfig['event']][$callConfig['apptDate']]) && empty($data[$callConfig['event']][$callConfig['indicator']]) &&
                (empty($callConfig['skip']) || (!$data[$callConfig['event']][$callConfig['skip']] && !$data[$prevEvent][$callConfig['skip']] && !$data[$callLogEvent][$callConfig['skip']] && !$data[$metadataEvent][$callConfig['skip']]))
            ) {
                $meta[$callConfig['id']] = [
                    "template" => 'nts',
                    "event_id" => $callConfig['event'],
                    "event" => $eventMap[$callConfig['event']],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
                    "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
            } elseif (!empty($meta[$callConfig['id']]) && !empty($data[$callConfig['event']][$callConfig['apptDate']])) {
                $meta[$callConfig['id']]['complete'] = true;
                $meta[$callConfig['id']]['completedBy'] = "REDCap";
                $this->projectLog("Need to Schedue call {$callConfig['id']} marked as complete, appointment was reschedueld.");
            }
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataAdhoc($project_id, $record, $payload)
    {
        $config = $this->loadCallTemplateConfig()["adhoc"];
        $config = array_filter($config, function ($x) use ($payload) {
            return $x['id'] == $payload['id'];
        });
        $config = end($config);
        $meta = $this->getCallMetadata($project_id, $record);
        if (empty($payload['date']))
            $payload['date'] = Date('Y-m-d');
        $reported = Date('Y-m-d H:i:s');
        $meta[$config['id'] . '||' . $reported] = [
            "start" => $payload['date'],
            "contactOn" => trim($payload['date'] . " " . $payload['time']),
            "reported" => $reported,
            "reporter" => $payload['reporter'],
            "reason" => $payload['reason'],
            "initNotes" => $payload['notes'],
            "template" => 'adhoc',
            "event_id" => '',
            "event" => '',
            "name" => $config['name'] . ' - ' . $config['reasons'][$payload['reason']],
            "instances" => [],
            "voiceMails" => 0,
            "maxVoiceMails" => $config['maxVoiceMails'],
            "maxVMperWeek" => $config['maxVoiceMailsPerWeek'],
            "hideAfterAttempt" => $config['hideAfterAttempt'],
            "complete" => false
        ];
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function resolveAdhoc($project_id, $record, $code)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        foreach ($meta as $callID => $callData) {
            if ($callData['complete'] || $callData['reason'] != $code)
                continue;
            $callData['complete'] = true; // Don't delete, just comp. Might need info for something
            $callData['completedBy'] = "REDCap";
            $this->projectLog("Adhoc call {$callID} marked as complete via API.");
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataPhoneVisit($project_id, $record)
    {
        $config = $this->loadCallTemplateConfig()["visit"];
        if (empty($config))
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach ($config as $i => $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, $callConfig['indicator'])[$record];
            if (empty($meta[$callConfig['id']]) && !empty($data[$callConfig['event']][$callConfig['indicator']])) {
                $meta[$callConfig['id']] = [
                    "template" => 'visit',
                    "event_id" => $callConfig['event'],
                    "event" => $eventMap[$callConfig['event']],
                    "end" => $data[$callConfig['event']][$callConfig['autoRemove']],
                    #"autoRemove" => !empty($autoRemoveField),        No longer recorderd on metadata
                    #"autoRemoveField" => $callConfig['autoRemove'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
                    "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
            }
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataUpdateCommon($project_id, $record)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (empty($meta))
            return; // We don't make the 1st metadata entry here.
        $data = $this->getAllCallData($project_id, $record);
        $instance = end(array_keys($data));
        $data = end($data); // get the data of the newest instance only
        $id = $data['call_id'];
        if (in_array($instance, $meta[$id]["instances"]))
            return;
        $meta[$id]["instances"][] = $instance;
        if ($data['call_left_message'][1] == '1')
            $meta[$id]["voiceMails"]++;
        if ($data['call_outcome'] == '1') {
            $meta[$id]['complete'] = true;
            $meta[$id]['completedBy'] = $this->framework->getUser()->getUsername();
        }
        $meta[$id]['callStarted'] = '';
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataNoCallsToday($project_id, $record, $call_id)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (!empty($meta) && !empty($meta[$call_id])) {
            if (!is_array($meta[$call_id]['noCallsToday'])) {
                $meta[$call_id]['noCallsToday'] = [];
            }
            $meta[$call_id]['noCallsToday'][] = date('Y-m-d');
            return $this->saveCallMetadata($project_id, $record, $meta);
        }
    }

    public function metadataCallStarted($project_id, $record, $call_id, $user)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (!empty($meta) && !empty($meta[$call_id])) {
            $meta[$call_id]['callStarted'] = date("Y-m-d H:i");
            $meta[$call_id]['callStartedBy'] = $user;
            return $this->saveCallMetadata($project_id, $record, $meta);
        }
    }

    public function metadataCallStartedUpdate($project_id, $record)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (empty($meta))
            return;
        $grace = strtotime('-' . $this->startedCallGrace . ' minutes'); // grace minutes ago
        $now = date("Y-m-d H:i");
        $user = $this->framework->getUser()->getUsername();
        foreach ($meta as $id => $call) {
            if (!$call['complete'] && ($call['callStartedBy'] == $user) && (strtotime($call['callStarted']) > $grace))
                $meta[$id]['callStarted'] = $now;
        }
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function metadataCallEnded($project_id, $record, $call_id)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (!empty($meta) && !empty($meta[$call_id])) {
            $meta[$call_id]['callStarted'] = '';
            return $this->saveCallMetadata($project_id, $record, $meta);
        }
    }

    /////////////////////////////////////////////////
    // Get, Save, and delete Data
    /////////////////////////////////////////////////

    public function deleteLastCallInstance($project_id, $record)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        $data = REDCap::getData($project_id, 'array', $record);
        $event = $this->getProjectSetting('call_log_event');
        $instance = end(array_keys($data[$record]['repeat_instances'][$event][$this->instrumentLower]));
        $instance = $instance ? $instance : '1';
        $instanceText = $instance != '1' ? ' AND instance= ' . $instance : ' AND isnull(instance)';
        foreach ($meta as $index => $call) {
            $tmp = $meta[$index]['instances'];
            $meta[$index]['instances'] = array_values(array_diff($call['instances'], array($instance)));
            // If we did remove a call then make sure we mark the call as incomplete.
            // We currently don't allow completed calls to actually be deleted though as there could be unforeseen issues.
            if ((count($tmp) > 0) && (count($tmp) != count($meta[$index]['instances'])))
                $meta[$index]['complete'] = false;
        }
        $fields = array_values(array_intersect(REDCap::getFieldNames($this->instrumentLower), array_keys($data[$record][$event])));
        db_query('DELETE FROM redcap_data WHERE project_id=' . $project_id . ' AND record=' . $record . $instanceText . ' AND (field_name="' . implode('" OR field_name="', $fields) . '");');
        return $this->saveCallMetadata($project_id, $record, $meta);
    }

    public function saveCallData($project_id, $record, $instance, $var, $val)
    {
        return REDCap::saveData($project_id, 'array', [$record => ['repeat_instances' => [$this->getProjectSetting('call_log_event') => [$this->instrumentLower => [$instance => [$var => $val]]]]]], 'overwrite');
    }

    public function getAllCallData($project_id, $record)
    {
        if (empty($_callData[$record])) {
            $event = $this->getProjectSetting('call_log_event');
            $data = REDCap::getData($project_id, 'array', $record, null, $event);
            $_callData[$record] = $data[$record]['repeat_instances'][$event][$this->instrumentLower];
            $_callData[$record] = empty($_callData[$record]) ? [1 => $data[$record][$event]] : $_callData[$record];
        }
        return $_callData[$record];
    }

    public function getCallMetadata($project_id, $record)
    {
        $metadata = REDCap::getData($project_id, 'array', $record, $this->metadataField)[$record][$this->getProjectSetting('metadata_event')][$this->metadataField];
        return empty($metadata) ? [] : json_decode($metadata, true);
    }

    public function saveCallMetadata($project_id, $record, $data)
    {
        return REDCap::saveData($project_id, 'array', [$record => [$this->getProjectSetting('metadata_event') => [$this->metadataField => json_encode($data)]]]);
    }

    /////////////////////////////////////////////////
    // Utlties and Config Loading
    /////////////////////////////////////////////////

    public function isInDAG($record)
    {
        $user_id = defined('USERID') ? USERID : false;
        if (!$user_id)
            return false;
        $user = REDCap::getUserRights($user_id)[$user_id];
        $super = $user['user_rights'] && $user['data_access_groups'];
        if ($super)
            return true;
        $user_group = $user['group_id'] ? $user['group_id'] : "";
        $record_group_name = reset(REDCap::getData($user['project_id'], 'array', $record, 'redcap_data_access_group', NULL, NULL, FALSE, TRUE)[$record])['redcap_data_access_group'];
        $all_group_names = REDCap::getGroupNames(true);
        if ($all_group_names[$user_group] == $record_group_name)
            return true;
        return $user_group == ""; # allow users without DAG restrictions access to all data
    }

    public function recentCallStarted($project_id, $record)
    {
        $meta = $this->getCallMetadata($project_id, $record);
        if (empty($meta))
            return '';
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

    private function getUserNameMap()
    {
        return array_map(function ($x) {
            return substr(explode('(', $x)[1], 0, -1);
        }, User::getUsernames(null, true));
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
            if ($template != "adhoc")
                continue;
            $reasons = $settings["adhoc_reason"][$i][0];
            if (empty($reasons))
                continue;
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
        if (!empty($this->_callTemplateConfig))
            return $this->_callTemplateConfig;
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
            $max = $settings["max_voice_mails"][$i];
            $maxWeek = $settings["max_voice_mails_per_week"][$i];
            $hide = $settings["hide_after_attempts"][$i];
            $commonConfig = [
                "id" => $settings["call_id"][$i],
                "name" => $settings["call_name"][$i],
                "maxVoiceMails" => $max ? (int)$max : 9999,
                "maxVoiceMailsPerWeek" => $maxWeek ? (int)$maxWeek : 9999,
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
                if (!empty($field)) {
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
            }

            // Load Follow up Config
            elseif ($template == "followup") {
                $event = $settings["followup_event"][$i][0];
                $field = $settings["followup_date"][$i][0];
                $days = (int)$settings["followup_days"][$i][0];
                $auto = $settings["followup_auto_remove"][$i][0];
                $length = (int)$settings["followup_length"][$i][0];
                $end = $settings["followup_end"][$i][0];
                if (!empty($field) && !empty($event) && !empty($days)) {
                    $followupConfig[] = array_merge([
                        "event" => $event,
                        "field" => $field,
                        "days" => $days,
                        "length" => $length,
                        "end" => $end,
                        "autoRemove" => $auto
                    ], $commonConfig);
                } elseif (!empty($field) && (!empty($days) || $days == "0")) {
                    $includeEvents = array_map('trim', explode(',', $settings["followup_include_events"][$i][0]));
                    foreach ($includeEvents as $eventName) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "field" => $field,
                            "days" => $days,
                            "length" => $length,
                            "end" => $end,
                            "autoRemove" => $auto
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
                $autoField = $settings["mcv_auto_remove"][$i][0];
                if (!empty($indicator) && !empty($dateField)) {
                    $includeEvents = array_map('trim', explode(',', $settings["mcv_include_events"][$i][0]));
                    foreach ($includeEvents as $eventName) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "indicator" => $indicator,
                            "apptDate" => $dateField,
                            "autoRemove" => $autoField
                        ], $commonConfig);
                        $arr['id'] = $arr['id'] . '|' . $eventName;
                        $arr['name'] = $arr['name'] . ' - ' . $eventNameMap[$eventName];
                        $mcvConfig[] = $arr;
                    }
                }
            }

            // Load Need to Schedule Visit Config
            elseif ($template == "nts") {
                $indicator = $settings["nts_indicator"][$i][0];
                $dateField = $settings["nts_date"][$i][0];
                $skipField = $settings["nts_skip"][$i][0];
                $window = $settings["nts_window_start_cron"][$i][0];
                if (!empty($indicator) && !empty($dateField)) {
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
            }

            // Load Adhoc Visit Config
            elseif ($template == "adhoc") {
                $reasons = $settings["adhoc_reason"][$i][0];
                if (!empty($reasons)) {
                    $arr = array_merge([
                        "reasons" => $this->explodeCodedValueText($reasons),
                    ], $commonConfig);
                    $adhocConfig[] = $arr;
                }
            }

            // Load Scheduled Phone Visit Config
            elseif ($template == "visit") {
                $indicator = $settings["visit_indicator"][$i][0];
                $autoField = $settings["visit_auto_remove"][$i][0];
                if (!empty($indicator)) {
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
        }
        $this->_callTemplateConfig = [
            "new" => $newEntryConfig,
            "reminder" => $reminderConfig,
            "followup" => $followupConfig,
            "mcv" => $mcvConfig,
            "nts" => $ntsConfig,
            "adhoc" => $adhocConfig,
            "visit" => $visitConfig
        ];
        return $this->_callTemplateConfig;
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
        }
        return $config;
    }

    public function loadTabConfig()
    {
        global $Proj;
        $allFields = [];
        $settings = $this->getProjectSettings();
        $orderMapping = $settings["tab_order"];
        if (count(array_filter($settings["tab_order"])) != count($settings["tab_order"])) {
            $orderMapping = range(0,count($settings["tab_name"]));
        }
        foreach ($settings["tab_name"] as $i => $tab_name) {
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
            foreach ($settings["tab_field"][$i] as $j => $field) {
                $name = $settings["tab_field_name"][$i][$j];
                $name = $name ? $name : trim($this->getDictionaryLabelFor($field), ":?");
                $validation = $Proj->metadata[$field]["element_validation_type"];
                $validation = $validation ? $validation : "";
                $default = $settings["tab_field_default"][$i][$j];
                $default = $default !== "" && !is_null($default) ? $default : "";
                $expanded =  $settings["tab_field_expanded"][$i][$j];
                $expanded = $expanded ? true : false;
                $tabConfig[$tabOrder]["fields"][$j] = [
                    "field" => $field,
                    "map" => $this->getDictionaryValuesFor($field),
                    "displayName" => $name,
                    "validation" => $validation,
                    "isFormStatus" => $Proj->isFormStatus($field),
                    "expanded" => $expanded,
                    "fieldType" => $Proj->metadata[$field]["element_type"],
                    "link" => $settings["tab_field_link"][$i][$j],
                    "linkedEvent" => $settings["tab_field_link_event"][$i][$j],
                    "linkedInstrument" => $settings["tab_field_link_instrument"][$i][$j],
                    "default" => $default
                ];
                $allFields[] = $field;
            }
        }
        
        // Re-index to be sure we are zero based
        ksort($tabConfig);
        $tabConfig = array_combine(range(0,count($tabConfig)-1),array_values($tabConfig));
        
        return [
            'config' => $tabConfig,
            'call2tabMap' => $call2TabMap,
            'tabNameMap' => $tabNameMap,
            'showBadges' => $settings["show_badges"],
            'allFields' => $allFields
        ];
    }

    /////////////////////////////////////////////////
    // Private Utility Functions
    /////////////////////////////////////////////////

    private function dateMath($date, $operation, $days)
    {
        $oldDate = date('Y-m-d', strtotime($date));
        $date = date('Y-m-d', strtotime($date . ' ' . $operation . $days . ' days'));
        if ($days > 5) {
            $day = Date("l", strtotime($date));
            if ($day == "Saturday" && $operation == "+") {
                $date = date('Y-m-d', strtotime($date . ' +2 days'));
            } elseif ($day == "Saturday" && $operation == "-") {
                $date = date('Y-m-d', strtotime($date . ' -1 days'));
            } elseif ($day == "Sunday" && $operation == "+") {
                $date = date('Y-m-d', strtotime($date . ' +1 days'));
            } elseif ($day == "Sunday" && $operation == "-") {
                $date = date('Y-m-d', strtotime($date . ' -2 days'));
            } else {
                $date = date('Y-m-d', strtotime($date));
            }
        } else { // Make sure its Business days
            while ($this->number_of_working_days($oldDate, $date) < $days) {
                $date = date('Y-m-d', strtotime($date . ' ' . $operation . '1 day'));
            }
        }
        return $date;
    }

    private function number_of_working_days($from, $to)
    {
        $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
        $holidays = ['*-12-25', '*-12-24', '*-12-31', '*-07-04', '*-01-01']; # Fixed holidays

        if ($from > $to) {
            $_to = $to;
            $to = $from;
            $from = $_to;
        }

        $from = new \DateTime($from);
        $to = new \DateTime($to);
        $interval = new \DateInterval('P1D');
        $periods = new \DatePeriod($from, $interval, $to);

        $days = 0;
        foreach ($periods as $period) {
            if (!in_array($period->format('N'), $workingDays)) continue;
            if (in_array($period->format('Y-m-d'), $holidays)) continue;
            if (in_array($period->format('*-m-d'), $holidays)) continue;
            $days++;
        }
        return $days;
    }

    private function roundDate($date, $round)
    {
        $day = Date("l", strtotime($date));
        if ($day == "Saturday" && $round == "up") {
            $date = date('Y-m-d', strtotime($date . ' +2 days'));
        } elseif ($day == "Saturday" && $round == "down") {
            $date = date('Y-m-d', strtotime($date . ' -1 days'));
        } elseif ($day == "Sunday" && $round == "up") {
            $date = date('Y-m-d', strtotime($date . ' +1 days'));
        } elseif ($day == "Sunday" && $round == "down") {
            $date = date('Y-m-d', strtotime($date . ' -2 days'));
        } else {
            $date = date('Y-m-d', strtotime($date));
        }
        return $date;
    }

    private function getEventNameMap()
    {
        $eventNames = array_values(REDCap::getEventNames());
        foreach (array_values(REDCap::getEventNames(true)) as $i => $unique)
            $eventMap[$unique] = $eventNames[$i];
        return $eventMap;
    }

    private function explodeCodedValueText($text)
    {
        $text = array_map(function ($line) {
            return array_map('trim', explode(',', $line));
        }, explode("\n", $text));
        return array_combine(array_column($text, 0), array_column($text, 1));
    }

    private function getDictionaryLabelFor($key)
    {
        $label = $this->getDataDictionary("array")[$key]['field_label'];
        if (empty($label)) {
            return $key;
        }
        return $label;
    }

    private function getDataDictionary($format = 'array')
    {
        if (!array_key_exists($format, $this->_dataDictionary)) {
            $this->_dataDictionary[$format] = \REDCap::getDataDictionary($format);
        }
        return $this->_dataDictionary[$format];
    }

    private function getDictionaryValuesFor($key)
    {
        return $this->flatten_type_values($this->getDataDictionary()[$key]['select_choices_or_calculations']);
    }

    private function comma_delim_to_key_value_array($value)
    {
        $arr = explode(', ', trim($value));
        $sliced = array_slice($arr, 1, count($arr) - 1, true);
        return array($arr[0] => implode(', ', $sliced));
    }

    private function array_flatten($array)
    {
        $return = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $return = $return + $this->array_flatten($value);
            } else {
                $return[$key] = $value;
            }
        }
        return $return;
    }

    private function flatten_type_values($value)
    {
        $split = explode('|', $value);
        $mapped = array_map(function ($value) {
            return $this->comma_delim_to_key_value_array($value);
        }, $split);
        return $this->array_flatten($mapped);
    }

    private function initGlobal()
    {
        $project_error = false;
        $call_event = $this->getProjectSetting('call_log_event');
        $meta_event = $this->getProjectSetting('metadata_event');
        if ($call_event) {
            $tmp = $this->instrumentLower;
            $sql = "SELECT * FROM redcap_events_repeat WHERE event_id = $call_event AND form_name = '$tmp';";
            $results = db_query($sql);
            if ($results && $results !== false && db_num_rows($results)) {
                $table = [];
                while ($row = db_fetch_assoc($results)) {
                    $table[] = $row;
                }
                if (count($table) != 1) {
                    $project_error = true;
                }
            }
        }
        $data = array(
            "modulePrefix" => $this->PREFIX,
            "events" => [
                "callLog" => [
                    "name" => REDCap::getEventNames(true, false, $call_event),
                    "id" => $call_event
                ],
                "metadata" => [
                    "name" => REDCap::getEventNames(true, false, $meta_event),
                    "id" => $meta_event
                ],
            ],
            "user" => USERID,
            "userNameMap" => $this->getUserNameMap(),
            "static" => [
                "instrument" => $this->instrumentName,
                "instrumentLower" => $this->instrumentLower,
                "instrumentMetadata" => $this->instrumentMeta,
                "record_id" => REDCap::getRecordIdField()
            ],
            "configError" => $project_error,
            "router" => $this->getURL('router.php')
        );
        echo "<script>var " . $this->module_global . " = " . json_encode($data) . ";</script>";
    }

    private function getUserNameListConfig()
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

    private function includeJs($path)
    {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }

    private function includeCss($path)
    {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '"/>';
    }

    private function passArgument($name, $value)
    {
        echo "<script>" . $this->module_global . "." . $name . " = " . json_encode($value) . ";</script>";
    }

    /////////////////////////////////////////////////
    // Private Utility Functions
    /////////////////////////////////////////////////

    private function projectLog($action)
    {
        $sql = null;
        REDCap::logEvent("Call Log", $action, $sql, $_GET['id'], $_GET['event_id'], $_GET['pid']);
    }

    public function ajaxLog()
    {
        $sql = null;
        $event = null;
        REDCap::logEvent("Call Log", $_POST['details'], $sql, $_POST['record'], $event, $_GET['pid']);
    }

    public function isNotBlank($value)
    {
        if ( is_array($value) ) {
            foreach ( $value as $k => $v ) {
                if ( $v != "" ) {
                    return True;
                }
            }
            return False;
        }
        //assume string
        return $value != "";
    }

    public function loadCallListData($skipDataPack = false)
    {
        $startTime = microtime(true);
        $project_id = $_GET['pid'];

        // Event IDs
        $callEvent = $this->getProjectSetting("call_log_event");
        $metaEvent = $this->getProjectSetting("metadata_event");

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

        // Construct the needed feilds (This is needed to save time. Loading all data takes several seconds, this is sub 1sec)
        $fields = array_merge(
            [
                REDCap::getRecordIdField(), $this->metadataField, $withdraw['var'], $withdraw['tmp']['var'],
                'call_open_date', 'call_left_message', 'call_requested_callback', 'call_callback_requested_by', 'call_notes', 'call_open_datetime', 'call_open_user_full_name', 'call_attempt', 'call_template', 'call_event_name', 'call_callback_date', 'call_callback_time'
            ],
            array_values($autoRemoveConfig),
            $tabs['allFields']
        );

        // Main Loop
        $records = $skipDataPack ? '-1' : null;
        $dataLoad = REDCap::getData($project_id, 'array', $records, $fields);
        foreach ($dataLoad as $record => $recordData) {

            // Check if the dag is empty or if it matches the User's DAG
            if (!$this->isInDAG($record))
                continue;

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
                if (($call['template'] == 'followup') && $call['autoRemove'] && ($call['end'] < $today))
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
