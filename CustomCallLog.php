<?php

namespace UWMadison\CustomCallLog;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

use REDCap;
use User;

function printToScreen($string) {
    ?><script>console.log(<?=json_encode($string); ?>);</script><?php
}

class CustomCallLog extends AbstractExternalModule  {
    
    private $module_prefix = 'CTRI_Custom_CallLog';
    private $module_global = 'CTRICallLog';
    private $module_name = 'CTRICallLog';
    
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
    
    // CDN Links
    private $datatablesCSS = "https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css";
    private $datatablesJS = "https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js";
    private $flatpickrCSS = "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css";
    private $flatpickrJS  = "https://cdn.jsdelivr.net/npm/flatpickr";
    private $cookieJS = "https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js";
    
    public function __construct() {
        parent::__construct();
        define("MODULE_DOCROOT", $this->getModulePath());
    }
    
    /////////////////////////////////////////////////
    // REDCap Hooks
    /////////////////////////////////////////////////
    
    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $hash, $response, $instance ) {
        if ( $instrument == $this->instrumentLower ) {
            // Update any metadata info that needs it
            $this->reportDisconnectedPhone($project_id, $record);
            $this->metadataUpdateCommon($project_id, $record);
        } else {
            // Check if the Phone issues are resolved
            $this->updateDisconnectedPhone($project_id, $record);
            // Check if we are a New Entry or not
            $this->metadataNewEntry($project_id, $record);
            // Check if we need to set (or remove) a new Follow up call (Checkout visit completed)
            $this->metadataFollowup($project_id, $record);
            // Check if we need to set a new Missed/Cancelled call (Checkout issue reported)
            $this->metadataMissedCancelled($project_id, $record);
            // Check if we need to set a new Need to Schedule call (Checkout vist completed)
            $this->metadataNeedToSchedule($project_id, $record);
            // Check if we need to make a Schedueld Phone Visit call log (Check in was started)
            $this->metadataPhoneVisit($project_id, $record);
            // Check if we need to extend the duration of the call flag
            $this->metadataCallStartedUpdate($project_id, $record);
        }
    }
    
    public function redcap_every_page_top($project_id) {
        if ( !defined("USERID") ) //Skip if user isn't logged in.
            return;

        $this->initCTRIglobal();
        $this->includeJs('js/every_page.js');
        
        // Record Home Page
        if (PAGE == 'DataEntry/record_home.php' && $_GET['id']) {
            $this->includeJs('js/record_home_page.js');
        }
        
        // Custom Config page
        if (strpos(PAGE, 'ExternalModules/manager/project.php') !== false && $project_id != NULL) {
            $this->includeJs('js/config.js');
        }

        // Index of Call List
        if (strpos(PAGE, 'ExternalModules/index.php') !== false && $project_id != NULL) {
            $this->includeCookies();
            $this->passArgument('usernameLists', $this->getUserNameListConfig());
            $this->passArgument('eventNameMap', $this->getEventNameMap());
            $this->passArgument('noCallsTodayPOST', $this->getURL('POST/setNoCallsToday.php'));
            $this->passArgument('callStartedPOST', $this->getURL('POST/setCallStarted.php'));
            $this->passArgument('callEndedPOST', $this->getURL('POST/setCallEnded.php'));
        }
    }
    
    public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
        $summary = $this->getProjectSetting('call_summary');
        if ( $instrument == $this->instrumentLower ) {
            $this->passArgument('metadata', $this->getCallMetadata($project_id, $record));
            $this->passArgument('metadataPOST', $this->getURL('POST/metadataSave.php'));
            $this->passArgument('calldataPOST', $this->getURL('POST/calldataSave.php'));
            $this->passArgument('calldeletePOST', $this->getURL('POST/callDelete.php'));
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->passArgument('eventNameMap', $this->getEventNameMap());
            $this->passArgument('adhoc', $this->loadAdhocTemplateConfig());
            $this->includeDataTables();
            $this->includeFlatpickr();
            $this->includeCss('css/log.css');
            $this->includeJs('js/summary_table.js');
            $this->includeJs('js/call_log.js');
        } elseif ( in_array($instrument, $summary) ) {
            $this->passArgument('metadata', $this->getCallMetadata($project_id, $record));
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->includeDataTables();
            $this->includeCss('css/log.css');
            $this->includeJs('js/summary_table.js');
        }
        $this->passArgument('recentCaller', $this->recentCallStarted($project_id, $record));
    }
    
    public function redcap_module_link_check_display($project_id, $link) {
        if ( strpos( $link['url'], 'index') !== false )
            return true;
        return null;
    }
    
    /////////////////////////////////////////////////
    // Issue Reporting
    /////////////////////////////////////////////////
    
    public function reportDisconnectedPhone($project_id, $record) {
        $config = $this->loadBadPhoneConfig();
        if ( $config['_missing'] )
            return;
        $data = REDCap::getData($project_id,'array',$record,[$config['flag'],$config['notes']],$config['event'])[$record][$config['event']];
        $callData = end($this->getAllCallData($project_id, $record));
        if ( $callData['call_disconnected'][1] == "1" ) {
            $write[$record][$config['event']][$config['flag']] = "1";
            $write[$record][$config['event']][$config['notes']] = $callData['call_open_date'].' '.$callData['call_open_time'].$callData['call_open_user_full_name'].': '.$callData['call_notes'].'\n\n'.$data[$record][$config['event']][$config['notes']];
            REDCap::saveData($project_id,'array',$write,'overwrite');
        }
    }
    
    public function updateDisconnectedPhone($project_id, $record) {
        $config = $this->loadBadPhoneConfig();
        if ( $config['_missing'] )
            return;
        $data = REDCap::getData($project_id,'array',$record,[$config['flag'],$config['notes'],$config['resolved']],$event)[$record][$config['event']];
        if ( $data[$config['resolved']][1] == "1" ) {
            $write[$record][$config['event']][$config['flag']] = "";
            $write[$record][$config['event']][$config['notes']] = "";
            $write[$record][$config['event']][$config['resolved']][1] = "0";
            REDCap::saveData($project_id,'array',$write,'overwrite');
        }
    }
    
    /////////////////////////////////////////////////
    // Metadata Updating / Creation
    /////////////////////////////////////////////////
    
    public function metadataNewEntry($project_id, $record) {
        // Also envoked via URL post for bulk load scripts
        $meta = $this->getCallMetadata($project_id, $record);
        if ( !empty($meta) )
            return;
        $config = $this->loadCallTemplateConfig()["new"];
        foreach( $config as $callConfig ) {
            $meta[$callConfig['id']] = [
                "template" => 'new',
                "event" => '',//None for new entry calls
                "event_id" => '',
                "name" => $callConfig['name'],
                "load" => date("Y-m-d H:i"),
                "instances" => [],
                "voiceMails" => 0,
                "maxVoiceMails" => $callConfig['maxVoiceMails'],
                "maxVMperWeek" => $callConfig['maxVoiceMailsPerWeek'],
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                "complete" => false
            ];
        }
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataFollowup($project_id, $record) {
        $config = $this->loadCallTemplateConfig()["followup"];
        if ( empty($config) )
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach( $config as $callConfig ) {
            $data = REDCap::getData($project_id,'array',$record,$callConfig['field'])[$record];
            if ( !empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == "" ) {
                //Anchor appt was removed, get rid of followup call too.
                unset($meta[$callConfig['id']]);
            } elseif (empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != ""  ) {
                // Anchor is set and the meta doesn't have the call id in it yet
                $start = date('Y-m-d', strtotime( $data[$callConfig['event']][$callConfig['field']].' +'.$callConfig['days'].' days'));
                $end = $callConfig['days'] + $callConfig['length'];
                $end = date('Y-m-d', strtotime( $data[$callConfig['event']][$callConfig['field']].' +'.$end.' days'));
                $meta[$callConfig['id']] = [
                    "start" => $this->roundDate($start, 'down'),
                    "end" => $this->roundDate($end, 'up'),
                    "template" => 'followup',
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
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataReminder($project_id, $record) {
        // Only envoked via URL Post from custom Scheduling solution
        $config = $this->loadCallTemplateConfig()["reminder"];
        if ( empty($config) )
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        $today = date('Y-m-d');
        foreach( $config as $callConfig ) {
            $data = REDCap::getData($project_id,'array',$record,[$callConfig['field'],$callConfig['removeVar']])[$record];
            if ( $data[$callConfig['removeEvent']][$callConfig['removeVar']] )
                continue;
            if ( !empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == "" ) {
                // Scheduled appt was removed, get rid of reminder call too.
                unset($meta[$callConfig['id']]);
            } 
            elseif ( !empty($meta[$callConfig['id']]) && ($data[$callConfig['event']][$callConfig['field']] <= $today) ) {
                // Appt is today, autocomplete the call so it stops showing up places, we might double set but it doesn't matter
                $meta[$callConfig['id']]['complete'] = true;
            }
            elseif (empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != ""  ) {
                // Scheduled appt exists and the meta doesn't have the call id in it yet
                $meta[$callConfig['id']] = [
                    "start" => $this->dateMath($data[$callConfig['event']][$callConfig['field']], '-', $callConfig['days']),
                    "end" => $this->dateMath($data[$callConfig['event']][$callConfig['field']], '+', $callConfig['days'] == 0 ? 365 : 0),
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
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataMissedCancelled($project_id, $record) {
        $config = $this->loadCallTemplateConfig()["mcv"];
        if ( empty($config) )
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach( $config as $callConfig ) {
            $data = REDCap::getData($project_id,'array',$record,[$callConfig['apptDate'],$callConfig['indicator']])[$record][$callConfig['event']];
            $idExact = $callConfig['id'].'||'.$data[$callConfig['apptDate']];
            if ( empty($meta[$idExact]) && !empty($data[$callConfig['apptDate']]) && !empty($data[$callConfig['indicator']]) ) {
                // Appt is set, Indicator is set, and metadata is missing, write it.
                $meta[$idExact] = [
                    "appt" => $data[$callConfig['apptDate']],
                    "template" => 'mcv',
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
            } elseif ( !empty($meta[$idExact]) && !empty($data[$callConfig['apptDate']]) && empty($data[$callConfig['indicator']]) ) {
                // The visit has been reschedueld for the exact previous time, or maybe user error
                // Previously we would usent those calls with 0 instances, but this leads to an issue if a mcv is reschedueld on the first try
                $meta[$idExact]['complete'] = true;
            }
            
            // Search for similar IDs and complete/remove them. We should only have 1 MCV call per event active on the call log
            foreach( $meta as $callID => $callData ) {
                if ( $callID == $idExact || $callData['complete'] || $callData['template']!="mcv" || $callData['event_id'] != $callConfig['event'] )
                    continue;
                if ( count($callData["instances"]) == 0 )
                    unset($meta[$callID]);
                else
                    $callData['complete'] = true;
            }
        }
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataNeedToSchedule($project_id, $record) {
        $config = $this->loadCallTemplateConfig()["nts"];
        if ( empty($config) )
            return;
        global $Proj;
        $orderedEvents = array_combine(array_map(function($x){return $x['day_offset'];},$Proj->eventInfo),array_keys($Proj->eventInfo));
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach( $config as $i=>$callConfig ) {
            $data = REDCap::getData($project_id,'array',$record,[$callConfig['apptDate'],$callConfig['indicator']])[$record];
            $prevEvent = $orderedEvents[array_search($callConfig['event'], $orderedEvents)-1];
            // If previous indicator is set (i.e. it was attended) and current event's appt_date is blank, and its not attended then set need to schedule.
            if ( empty($meta[$callConfig['id']]) && !empty($data[$prevEvent][$callConfig['indicator']]) && empty($data[$callConfig['event']][$callConfig['apptDate']]) && empty($data[$callConfig['event']][$callConfig['indicator']])) {
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
            } elseif ( !empty($meta[$callConfig['id']]) && !empty($data[$callConfig['event']][$callConfig['apptDate']]) ) {
                $meta[$callConfig['id']]['complete'] = true;
            }
        }
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataAdhoc($project_id, $record, $payload) {
        $config = $this->loadCallTemplateConfig()["adhoc"];
        $config = array_filter($config, function($x) use ($payload) {return $x['id']==$payload['id'];});
        $config = end($config);
        $meta = $this->getCallMetadata($project_id, $record);
        if ( empty($payload['date']) )
            $payload['date'] = Date('Y-m-d');
        $reported = Date('Y-m-d H:i:s');
        $meta[$config['id'].'||'.$reported] = [
            "start" => $payload['date'],
            "contactOn" => trim($payload['date']." ".$payload['time']),
            "reported" => $reported,
            "reporter" => $payload['reporter'],
            "reason" => $payload['reason'],
            "initNotes" => $payload['notes'],
            "template" => 'adhoc',
            "event_id" => '',
            "event" => '',
            "name" => $config['name'].' - '.$config['reasons'][$payload['reason']],
            "instances" => [],
            "voiceMails" => 0,
            "maxVoiceMails" => $config['maxVoiceMails'],
            "maxVMperWeek" => $config['maxVoiceMailsPerWeek'],
            "hideAfterAttempt" => $config['hideAfterAttempt'],
            "complete" => false
        ];
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function resolveAdhoc($project_id, $record, $code) {
        $meta = $this->getCallMetadata($project_id, $record);
        foreach( $meta as $callID => $callData ) {
            if ( $callData['complete'] || $callData['reason']!=$code )
                continue;
            $callData['complete'] = true; // Don't delete, just comp. Might need info for something
        }
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataPhoneVisit($project_id, $record) {
        $config = $this->loadCallTemplateConfig()["visit"];
        if ( empty($config) )
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach( $config as $i=>$callConfig ) {
            $data = REDCap::getData($project_id,'array',$record,$callConfig['indicator'])[$record];
            if ( empty($meta[$callConfig['id']]) && !empty($data[$callConfig['event']][$callConfig['indicator']]) ) {
                $meta[$callConfig['id']] = [
                    "template" => 'visit',
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
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataUpdateCommon($project_id, $record) {
        $meta = $this->getCallMetadata($project_id, $record);
        if ( empty($meta) ) 
            return; // We don't make the 1st metadata entry here.
        $data = $this->getAllCallData($project_id, $record);
        $instance = end(array_keys($data));
        $data = end($data); // get the data of the newest instance only
        $id = $data['call_id'];
        if ( in_array($instance, $meta[$id]["instances"]) )
            return;
        $meta[$id]["instances"][] = $instance;
        if ( $data['call_left_message'][1] == '1' )
            $meta[$id]["voiceMails"]++;
        if ( $data['call_outcome'] == '1' )
            $meta[$id]['complete'] = true;
        $meta[$id]['callStarted'] = '';
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataNoCallsToday($project_id, $record, $call_id) {
        $meta = $this->getCallMetadata($project_id, $record);
        if ( !empty($meta) && !empty($meta[$call_id]) ) {
            $meta[$call_id]['noCallsToday'] = date('Y-m-d');
            $this->saveCallMetadata($project_id, $record, $meta);
        }
    }
    
    public function metadataCallStarted($project_id, $record, $call_id, $user) {
        $meta = $this->getCallMetadata($project_id, $record);
        if ( !empty($meta) && !empty($meta[$call_id]) ) {
            $meta[$call_id]['callStarted'] = date("Y-m-d H:i");
            $meta[$call_id]['callStartedBy'] = $user;
            $this->saveCallMetadata($project_id, $record, $meta);
        }
    }
    
    public function metadataCallStartedUpdate($project_id, $record) {
        $meta = $this->getCallMetadata($project_id, $record);
        if ( empty($meta) )
            return;
        $grace = strtotime('-'.$module->startedCallGrace.' minutes');
        $now = date("Y-m-d H:i");
        $user = $this->framework->getUser()->getUsername();
        foreach( $meta as $id=>$call ) {
            if ( !$call['complete'] && ($call['callStartedBy'] == $user) && (strtotime($call['callStarted']) > $grace) )
                $meta[$id]['callStarted'] = $now;
        }
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function metadataCallEnded($project_id, $record, $call_id) {
        $meta = $this->getCallMetadata($project_id, $record);
        if ( !empty($meta) && !empty($meta[$call_id]) ) {
            $meta[$call_id]['callStarted'] = '';
            $this->saveCallMetadata($project_id, $record, $meta);
        }
    }
    
    /////////////////////////////////////////////////
    // Get, Save, and delete Data
    /////////////////////////////////////////////////
    
    public function deleteLastCallInstance($project_id, $record) {
        $meta = $this->getCallMetadata($project_id, $record);
        $data = REDCap::getData($project_id,'array',$record);
        $event = $this->getProjectSetting('call_log_event');
        $instance = end(array_keys($data[$record]['repeat_instances'][$event][$this->instrumentLower]));
        $instance = $instance ? $instance : '1';
        foreach( $meta as $index => $call ) {
            $tmp = $meta[$index]['instances'];
            $meta[$index]['instances'] = array_values(array_diff($call['instances'], array($instance)));
            // If we did remove a call then make sure we mark the call as incomplete.
            // We currently don't allow completed calls to actually be deleted though as there could be unforeseen issues.
            if ( (count($tmp) > 0) && (count($tmp) != count($meta[$index]['instances'])) )
                $meta[$index]['complete'] = false;
        }
        if ( $instance == '1' ) {
            $fields = array_values(array_intersect( REDCap::getFieldNames($this->instrumentLower), array_keys($data[$record][$event]) ));
            db_query( 'DELETE FROM redcap_data WHERE project_id='. $project_id . ' AND record=' . $record . ' AND (field_name="' . implode('" OR field_name="', $fields) . '");' );
        } else 
            db_query( 'DELETE FROM redcap_data WHERE project_id='. $project_id . ' AND record=' . $record . ' AND instance=' . $instance . ';' );
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    public function saveCallData($project_id, $record, $instance, $var, $val) {
        $response = REDCap::saveData($project_id,'array', [$record=>['repeat_instances'=>[$this->getProjectSetting('call_log_event')=>[$this->instrumentLower=>[$instance=>[$var=>$val]]]]]],'overwrite');
        echo json_encode($response);
    }
    
    public function getAllCallData($project_id, $record) {
        if ( empty($_callData[$record]) ) { 
            $event = $this->getProjectSetting('call_log_event');
            $data = REDCap::getData($project_id,'array',$record,null,$event);
            $_callData[$record] = $data[$record]['repeat_instances'][$event][$this->instrumentLower];
            $_callData[$record] = empty($_callData[$record]) ? [1=>$data[$record][$event]] : $_callData[$record];
        }
        return $_callData[$record];
    }
    
    public function getCallMetadata($project_id, $record) {
        $metadata = REDCap::getData($project_id,'array',$record,$this->metadataField)[$record][$this->getProjectSetting('metadata_event')][$this->metadataField];
        return empty($metadata) ? [] : json_decode($metadata, true);
    }
    
    public function saveCallMetadata($project_id, $record, $data) {
        $response = REDCap::saveData($project_id,'array', [$record=>[$this->getProjectSetting('metadata_event')=>[$this->metadataField=>json_encode($data)]]]);
        printToScreen($response);
    }
    
    /////////////////////////////////////////////////
    // Utlties and Config Loading
    /////////////////////////////////////////////////
    
    public function recentCallStarted($project_id, $record) {
        $meta = $this->getCallMetadata($project_id, $record);
        if ( empty($meta) )
            return '';
        $grace = strtotime('-'.$module->startedCallGrace.' minutes');
        $user = $this->framework->getUser()->getUsername();
        foreach( $meta as $call ) {
            if ( !$call['complete'] && ($call['callStartedBy'] != $user) && ((time() - strtotime($call['callStarted'])/60) < $grace) )
                return $call['callStartedBy'];
        }
        return '';
    }
    
    public function getUserNameMap() {
        return array_map(function($x){return substr(explode('(',$x)[1],0,-1);},User::getUsernames(null,true));
    }
    
    public function loadBadPhoneConfig() {
        $config = [$this->getProjectSetting('bad_phone_event'),$this->getProjectSetting('bad_phone_flag'),
                   $this->getProjectSetting('bad_phone_notes'),$this->getProjectSetting('bad_phone_resolved')];
        $missing = count(array_filter($config)) == count($config);
        return [
            'event' => $config[0],
            'flag' => $config[1],
            'notes' => $config[2],
            'resolved' => $config[3],
            '_missing' => $missing
        ];
    }
    
    public function loadAdhocTemplateConfig() {
        foreach( $this->getProjectSetting("call_template") as $i => $template) {
            if ( $template != "adhoc" ) 
                continue;
            $reasons = $this->getProjectSetting("adhoc_reason")[$i][0];
            if ( empty($reasons) )
                continue;
            $config[$this->getProjectSetting("call_id")[$i]] = [ 
                "id" => $this->getProjectSetting("call_id")[$i],
                "name" => $this->getProjectSetting("call_name")[$i],
                "reasons" => $this->explodeCodedValueText($reasons)
            ];
        }
        return [
            'post' => $this->getURL('POST/adhocLoad.php'),
            'config' => $config
        ];
    }
    
    public function loadCallTemplateConfig() {
        if ( !empty($this->_callTemplateConfig) )
            return $this->_callTemplateConfig;
        $eventNameMap = $this->getEventNameMap();
        $newEntryConfig = [];
        $reminderConfig = [];
        $followupConfig = [];
        $mcvConfig = [];
        $ntsConfig = [];
        $adhocConfig = [];
        $visitConfig = [];
        foreach( $this->getProjectSetting("call_template") as $i => $template) {
            $max = $this->getProjectSetting("max_voice_mails")[$i];
            $maxWeek = $this->getProjectSetting("max_voice_mails_per_week")[$i];
            $hide = $this->getProjectSetting("hide_after_attempts")[$i];
            $commonConfig = [
                "id" => $this->getProjectSetting("call_id")[$i],
                "name" => $this->getProjectSetting("call_name")[$i],
                "maxVoiceMails" => $max ? (int)$max : 9999,
                "maxVoiceMailsPerWeek" => $maxWeek ? (int)$maxWeek : 9999,
                "hideAfterAttempt" => $hide ? (int)$hide : 9999
            ];

            // Load New Entry Config
            if ( $template == "new" ) {
                $newEntryConfig[] = $commonConfig;
            }
            
            // Load Reminder Config
            elseif ( $template == "reminder" ) {
                $field = $this->getProjectSetting("reminder_variable")[$i][0];
                if ( !empty($field) ){
                    $includeEvents = array_map('trim', explode(',',$this->getProjectSetting("reminder_include_events")[$i][0])); 
                    foreach( $includeEvents as $eventName ) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "field" => $field,
                            "days" => (int)$this->getProjectSetting("reminder_days")[$i][0],
                            "removeEvent" => $this->getProjectSetting("reminder_remove_event")[$i][0],
                            "removeVar" => $this->getProjectSetting("reminder_remove_var")[$i][0]
                        ], $commonConfig);
                        $arr['id'] = $arr['id'].'|'.$eventName;
                        $arr['name'] = $arr['name'].' - '.$eventNameMap[$eventName];
                        $reminderConfig[] = $arr;
                    }
                }
            }
            
            // Load Follow up Config
            elseif ( $template == "followup" ) {
                $event = $this->getProjectSetting("followup_event")[$i][0];
                $field = $this->getProjectSetting("followup_date")[$i][0];
                $days = $this->getProjectSetting("followup_days")[$i][0];
                if ( !empty($field) && !empty($event) && !empty($days) ) {
                    $followupConfig[] = array_merge([
                        "event" => $event,
                        "field" => $field,
                        "days" => (int)$this->getProjectSetting("followup_days")[$i][0],
                        "length" => (int)$this->getProjectSetting("followup_length")[$i][0]
                    ], $commonConfig);
                } elseif ( !empty($field) && !empty(days) ) {
                    $includeEvents = array_map('trim', explode(',',$this->getProjectSetting("followup_include_events")[$i][0])); 
                    foreach( $includeEvents as $eventName ) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "field" => $field,
                            "days" => (int)$this->getProjectSetting("followup_days")[$i][0],
                            "length" => (int)$this->getProjectSetting("followup_length")[$i][0]
                        ], $commonConfig);
                        $arr['id'] = $arr['id'].'|'.$eventName;
                        $arr['name'] = $arr['name'].' - '.$eventNameMap[$eventName];
                        $followupConfig[] = $arr;
                    }
                }
            }
            
            // Load Missed/Cancelled Visit Config
            elseif ( $template == "mcv" ) {
                $indicator = $this->getProjectSetting("mcv_indicator")[$i][0];
                $dateField = $this->getProjectSetting("mcv_date")[$i][0];
                if ( !empty($indicator) && !empty($dateField) ) {
                    $includeEvents = array_map('trim', explode(',',$this->getProjectSetting("mcv_include_events")[$i][0])); 
                    foreach( $includeEvents as $eventName ) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "indicator" => $indicator,
                            "apptDate" => $dateField
                        ], $commonConfig);
                        $arr['id'] = $arr['id'].'|'.$eventName;
                        $arr['name'] = $arr['name'].' - '.$eventNameMap[$eventName];
                        $mcvConfig[] = $arr;
                    }
                }
            }
            
            // Load Need to Schedule Visit Config
            elseif ( $template == "nts" ) {
                $indicator = $this->getProjectSetting("nts_indicator")[$i][0];
                $dateField = $this->getProjectSetting("nts_date")[$i][0];
                if ( !empty($indicator) && !empty($dateField) ) {
                    $includeEvents = array_map('trim', explode(',',$this->getProjectSetting("nts_include_events")[$i][0]));
                    foreach( $includeEvents as $eventName ) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "indicator" => $indicator,
                            "apptDate" => $dateField
                        ], $commonConfig);
                        $arr['id'] = $arr['id'].'|'.$eventName;
                        $arr['name'] = $arr['name'].' - '.$eventNameMap[$eventName];
                        $ntsConfig[] = $arr;
                    }
                }
            }
            
            // Load Adhoc Visit Config
            elseif ( $template == "adhoc" ) {
                $reasons = $this->getProjectSetting("adhoc_reason")[$i][0];
                if ( !empty($reasons) ) {
                    $arr = array_merge([
                        "reasons" => $this->explodeCodedValueText($reasons),
                    ], $commonConfig);
                    $adhocConfig[] = $arr;
                }
            }
            
            // Load Scheduled Phone Visit Config
            elseif ( $template == "visit" ) {
                $indicator = $this->getProjectSetting("visit_indicator")[$i][0];
                if ( !empty($indicator) ) {
                    $includeEvents = array_map('trim', explode(',',$this->getProjectSetting("visit_include_events")[$i][0]));
                    foreach( $includeEvents as $eventName ) {
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "indicator" => $indicator
                        ], $commonConfig);
                        $arr['id'] = $arr['id'].'|'.$eventName;
                        $arr['name'] = $arr['name'].' - '.$eventNameMap[$eventName];
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
    
    public function loadTabConfig() {
        global $Proj;
        foreach( $this->getProjectSetting("tab_name") as $i => $tab_name) {
            $calls = $this->getProjectSetting("tab_calls_included")[$i];
            $tab_id = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '_',strtolower($tab_name)));
            $tabConfig[$i] = [
               "tab_name" => $tab_name,
               "included_calls" => $calls,
               "tab_id" => $tab_id,
               "fields" => $this->getProjectSetting("tab_field")[$i],
               "showFollowupWindows" => $this->getProjectSetting("tab_includes_followup")[$i] == '1',
               "showMissedDateTime" => $this->getProjectSetting("tab_includes_mcv")[$i] == '1',
               "showAdhocDates" => $this->getProjectSetting("tab_includes_adhoc")[$i] == '1'
            ];
            $calls = array_map('trim',explode(',',$calls));
            foreach( $calls as $call ) {
                $call2TabMap[$call] = $tab_id;
            }
            foreach( $this->getProjectSetting("tab_field")[$i] as $j => $field ) {
                $name = $this->getProjectSetting("tab_field_name")[$i][$j];
                $name = $name ? $name : trim($this->getDictionaryLabelFor($field), ":?");
                $validation = $Proj->metadata[$field]["element_validation_type"];
                $validation = $validation ? $validation : "";
                $default = $this->getProjectSetting("tab_field_default")[$i][$j];
                $default = $default !== "" && !is_null($default) ? $default : "";
                $expanded =  $this->getProjectSetting("tab_field_expanded")[$i][$j];
                $expanded = $expanded ? true : false;
                $tabConfig[$i]["fields"][$j] = [
                    "field" => $field,
                    "map" => $this->getDictionaryValuesFor($field),
                    "displayName" => $name,
                    "validation" => $validation,
                    "isFormStatus" => $Proj->isFormStatus($field),
                    "expanded" => $expanded,
                    "fieldType" => $Proj->metadata[$field]["element_type"],
                    "link" => $this->getProjectSetting("tab_field_link")[$i][$j],
                    "linkedEvent" => $this->getProjectSetting("tab_field_link_event")[$i][$j],
                    "linkedInstrument" => $this->getProjectSetting("tab_field_link_instrument")[$i][$j],
                    "default" => $default
                ];
            }
            
        }
        return [
            'config' => $tabConfig,
            'call2tabMap' => $call2TabMap
        ];
    }
    
    /////////////////////////////////////////////////
    // Private Utility Functions
    /////////////////////////////////////////////////
    
    private function dateMath($date, $operation, $days) {
        $oldDate = date('Y-m-d', strtotime( $date ) );
        $date = date('Y-m-d', strtotime( $date.' '.$operation.$days.' days'));
        if ( $days > 5 ) {
            $day = Date("l", strtotime($date));
            if ( $day == "Saturday" && $operation == "+" ) {
                $date = date('Y-m-d', strtotime($date.' +2 days'));
            } elseif ( $day == "Saturday" && $operation == "-" ) {
                $date = date('Y-m-d', strtotime($date.' -1 days'));
            } elseif( $day == "Sunday" && $operation == "+" ) {
                $date = date('Y-m-d', strtotime($date.' +1 days'));
            } elseif( $day == "Sunday" && $operation == "-" ) {
                $date = date('Y-m-d', strtotime($date.' -2 days'));
            } else {
                $date = date('Y-m-d', strtotime($date));
            }
        } else { // Make sure its Business days
            while ( $this->number_of_working_days($oldDate, $date) < $days ) {
                $date = date('Y-m-d', strtotime( $date.' '.$operation.'1 day'));
            }
        }
        return $date;
    }
    
    private function number_of_working_days($from, $to) {
        $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
        $holidayDays = ['*-12-25', '*-12-24', '*-12-31', '*-07-04', '*-01-01']; # variable and fixed holidays
        
        if ( $from > $to ) {
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
            if (in_array($period->format('Y-m-d'), $holidayDays)) continue;
            if (in_array($period->format('*-m-d'), $holidayDays)) continue;
            $days++;
        }
        return $days;
    }
    
    private function roundDate($date, $round) {
        $day = Date("l", strtotime($date));
        if ( $day == "Saturday" && $round == "up" ) {
            $date = date('Y-m-d', strtotime($date.' +2 days'));
        } elseif ( $day == "Saturday" && $round == "down" ) {
            $date = date('Y-m-d', strtotime($date.' -1 days'));
        } elseif( $day == "Sunday" && $round == "up" ) {
            $date = date('Y-m-d', strtotime($date.' +1 days'));
        } elseif( $day == "Sunday" && $round == "down" ) {
            $date = date('Y-m-d', strtotime($date.' -2 days'));
        } else {
            $date = date('Y-m-d', strtotime($date));
        }
        return $date;
    }
    
    private function getEventNameMap() {
        $eventNames = array_values(REDCap::getEventNames());
        foreach( array_values(REDCap::getEventNames(true)) as $i => $unique )
            $eventMap[$unique] = $eventNames[$i];
        return $eventMap;
    }
    
    private function explodeCodedValueText($text) {
        $text = array_map( function($line) { return array_map('trim',explode(',',$line)); }, explode("\n", $text));
        return array_combine( array_column($text,0), array_column($text,1) );
    }
    
    private function getDictionaryLabelFor($key) {
        $label = $this->getDataDictionary("array")[$key]['field_label'];
        if (empty($label)) {
            return $key;
        }
        return $label;
    }
    
    private function getDataDictionary($format = 'array') {
        if(!array_key_exists($format, $this->_dataDictionary)){
            $this->_dataDictionary[$format] = \REDCap::getDataDictionary($format);
        }
        $dictionaryToReturn = $this->_dataDictionary[$format];
        return $dictionaryToReturn;
    }
    
    private function getDictionaryValuesFor($key) {
        return $this->flatten_type_values($this->getDataDictionary()[$key]['select_choices_or_calculations']);
    }
    
    private function comma_delim_to_key_value_array($value) {
        $arr = explode(', ', trim($value));
        $sliced = array_slice($arr, 1, count($arr)-1, true);
        return array($arr[0] => implode(', ', $sliced));
    }

    private function array_flatten($array) {
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
    
    private function flatten_type_values($value) {
        $split = explode('|', $value);
        $mapped = array_map(function ($value) { return $this->comma_delim_to_key_value_array($value); }, $split);
        $result = $this->array_flatten($mapped);
        return $result;
    }
    
    private function initCTRIglobal() {
        $call_event = $this->getProjectSetting('call_log_event');
        $meta_event = $this->getProjectSetting('metadata_event');
        $data = array(
            "modulePrefix" => $this->module_prefix,
            "events" => [
                "callLog" => [
                    "name" => REDCap::getEventNames(true,false,$call_event),
                    "id" => $call_event
                ],
                "metadata" => [
                    "name" => REDCap::getEventNames(true,false,$meta_event),
                    "id" => $meta_event
                ],
            ],
            "userNameMap" => $this->getUserNameMap(),
            "static" => [
                "instrument" => $this->instrumentName,
                "instrumentLower" => $this->instrumentLower,
                "instrumentMetadata" => $this->instrumentMeta,
                "record_id" => REDCap::getRecordIdField()
            ]
        );
        echo "<script>var ".$this->module_global." = ".json_encode($data).";</script>";
    }
    
    private function getUserNameListConfig() {
        $config = [];
        $include = $this->getProjectSetting('username_include');
        $exclude = $this->getProjectSetting('username_exclude');
        foreach( $this->getProjectSetting('username_field') as $index => $field ) {
            $config[$field] = [
                'include' => $include[$index],
                'exclude' => $exclude[$index]
            ];
        }
        return $config;
    }
    
    private function includeFlatpickr() {
        echo '<link rel="stylesheet" href="'.$this->flatpickrCSS.'">';
        echo '<script src="'.$this->flatpickrJS.'"></script>';
    }
    
    private function includeCookies() {
        echo '<script type="text/javascript" src="'.$this->cookieJS.'"></script>';
    }
    
    public function includeDataTables() {
        echo '<link rel="stylesheet" href="'.$this->datatablesCSS.'"/>';
        echo '<script type="text/javascript" src="'.$this->datatablesJS.'"></script>';
    }
    
    private function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }
    
    public function includeCss($path) {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '"/>';
    }
    
    private function passArgument($name, $value) {
        echo "<script>".$this->module_global.".".$name." = ".json_encode($value).";</script>";
    }
}