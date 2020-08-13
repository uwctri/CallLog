<?php

namespace UWM\CustomCallLog;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

use REDCap;
use User;

function printToScreen($string) {
?>
    <script type='text/javascript'>
       $(function() {
          console.log(<?=json_encode($string); ?>);
       });
    </script>
    <?php
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
        }
    }
    
    public function redcap_every_page_top($project_id) {
        $this->initCTRIglobal();
        $this->includeJs('js/every_page.js');
        
        // Record Home Page
        if (PAGE == 'DataEntry/record_home.php' && $_GET['id']) {
            $templateConfig = $this->loadCallTemplateConfig();
            $this->includeJs('js/record_home_page.js');
        }
        
        // Custom Config page
        if (strpos(PAGE, 'ExternalModules/manager/project.php') !== false && $project_id != NULL) {
            $this->includeJs('js/config.js');
        }
    }

    public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
        if ( $instrument == $this->instrumentLower ) {
            $this->passArgument('metadata', $this->getCallMetadata($project_id, $record));
            $this->passArgument('data', $this->getAllCallData($project_id, $record));
            $this->passArgument('eventNameMap', $this->getEventNameMap());
            $this->passArgument('userNameMap', array_map(function($x){return substr(explode('(',$x)[1],0,-1);},User::getUsernames(null,true)));
            $this->includeJs('js/call_log.js');
        } 
    }
    
    public function redcap_module_link_check_display($project_id, $link) {
        if ( strpos( $link['url'], 'newEntryLoad') !== false || strpos( $link['url'], 'reminderLoad') !== false )
            return null;
        return true;
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
                "name" => $callConfig['name'],
                "load" => date("Y-m-d H:i"),
                "instances" => [],
                "voiceMails" => 0,
                "maxVoiceMails" => $callConfig['maxVoiceMails'],
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
                $end = $callConfig['days'] + $callConfig['length'];
                $meta[$callConfig['id']] = [
                    "start" => date('Y-m-d', strtotime( $data[$callConfig['event']][$callConfig['field']].' +'.$callConfig['days'].' days')),
                    "end" => date('Y-m-d', strtotime( $data[$callConfig['event']][$callConfig['field']].' +'.$end.' days')),
                    "template" => 'followup',
                    "event" => $eventMap[$callConfig['event']],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
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
        foreach( $config as $callConfig ) {
            $data = REDCap::getData($project_id,'array',$record,[$callConfig['field'],$callConfig['removeVar']])[$record];
            if ( $data[$callConfig['removeEvent']][$callConfig['removeVar']] )
                continue;
            if ( !empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == "" ) {
                // Scheduled appt was removed, get rid of reminder call too.
                unset($meta[$callConfig['id']]);
            } elseif (empty($meta[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != ""  ) {
                // Scheduled appt exists and the meta doesn't have the call id in it yet
                $meta[$callConfig['id']] = [
                    "start" => date('Y-m-d', strtotime( $data[$callConfig['event']][$callConfig['field']].' -'.$callConfig['days'].' days')),
                    "end" => $data[$callConfig['event']][$callConfig['field']],
                    "template" => 'reminder',
                    "event" => $eventMap[$callConfig['event']],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
            }
        }
    }
    
    public function metadataMissedCancelled($project_id, $record) {
        $config = $this->loadCallTemplateConfig()["mcv"];
        if ( empty($config) )
            return;
        $meta = $this->getCallMetadata($project_id, $record);
        $eventMap = REDCap::getEventNames(true);
        foreach( $config as $callConfig ) {
            $data = REDCap::getData($project_id,'array',$record,[$callConfig['apptDate'],$callConfig['indicator']])[$record][$callConfig['event']];
            $id = $callConfig['id'].'||'.$data[$callConfig['apptDate']];
            if ( !empty($data[$callConfig['apptDate']]) && !empty($data[$callConfig['indicator']]) && empty($meta['id']) ) {
                // Appt is set, Indicator is set, and metadata is missing, write it.
                $meta[$callConfig['id'].'||'.$callConfig['apptDate']] = [
                    "appt" => $data[$callConfig['apptDate']],
                    "template" => 'mvc',
                    "event" => $eventMap[$callConfig['event']],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "maxVoiceMails" => $callConfig['maxVoiceMails'],
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
            } // No way to remove old MCVs as appt date is gone
        }
    }
    
    public function metadataUpdateCommon($project_id, $record) {
        $meta = $this->getCallMetadata($project_id, $record);
        if ( empty($meta) ) 
            return; // We don't make the 1st metadata entry here.
        $data = $this->getAllCallData($project_id, $record);
        $instance = end(array_keys($data));
        $data = end($data); // get the data of the newest instance only
        $id = $data['call_id'];
        if ( $meta[$id]['complete'] || in_array($instance, $meta[$id]["instances"]) )
            return;
        $meta[$id]["instances"][] = $instance;
        if ( $data['call_left_message'][1] == '1' )
            $meta[$id]["voiceMails"]++;
        if ( $data['call_outcome'] == '1' )
            $meta[$id]['complete'] = true;
        $this->saveCallMetadata($project_id, $record, $meta);
    }
    
    /////////////////////////////////////////////////
    // Utlties and Config Loading
    /////////////////////////////////////////////////
    
    public function getAllCallData($project_id, $record) {
        if ( empty($_callData[$record]) ) { 
            $event = $this->getProjectSetting('call_log_event');
            $_callData[$record] = REDCap::getData($project_id,'array',$record,null,$event)[$record]['repeat_instances'][$event][$this->instrumentLower];
        }
        return $_callData[$record];
    }
    
    public function getCallMetadata($project_id, $record) {
        $metadata = REDCap::getData($project_id,'array',$record,$this->metadataField)[$record][$this->getProjectSetting('metadata_event')][$this->metadataField];
        return empty($metadata) ? [] : json_decode($metadata, true);
    }
    
    public function saveCallMetadata($project_id, $record, $data) {
        $response = REDCap::saveData($project_id,'array', [$record=>[$this->getProjectSetting('metadata_event')=>[$this->metadataField=>json_encode($data)]]]);
    }
    
    public function getEventNameMap() {
        $eventNames = array_values(REDCap::getEventNames());
        foreach( array_values(REDCap::getEventNames(true)) as $i => $unqie ) {
            $eventMap[$unqie] = $eventNames[$i];
        }
        return $eventMap;
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
    
    public function loadCallTemplateConfig() {
        if ( !empty($this->_callTemplateConfig) )
            return $this->_callTemplateConfig;
        $eventNameMap = $this->getEventNameMap();
        $newEntryConfig = [];
        $reminderConfig = [];
        $followupConfig = [];
        $mcvConfig = [];
        foreach( $this->getProjectSetting("call_template") as $i => $template) {
            $max = $this->getProjectSetting("max_voice_mails")[$i];
            $hide = $this->getProjectSetting("hide_after_attempts")[$i];
            $commonConfig = [
                "id" => $this->getProjectSetting("call_id")[$i],
                "name" => $this->getProjectSetting("call_name")[$i],
                "maxVoiceMails" => $max ? (int)$max : 9999,
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
                    $skipEvents = array_map('trim', explode(',',$this->getProjectSetting("reminder_skip_events")[$i][0])); 
                    foreach( REDCap::getEventNames(true) as $eventID => $eventName ) {
                        if ( in_array($eventName, $skipEvents) )
                            continue;
                        $arr = array_merge([
                            "event" => $eventID,
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
                $field = $this->getProjectSetting("followup_field")[$i][0];
                $days = $this->getProjectSetting("followup_days")[$i][0];
                if ( !empty($field) && !empty($event) && !empty($days) ) {
                    $followupConfig[] = array_merge([
                        "event" => $event,
                        "field" => $field,
                        "days" => (int)$this->getProjectSetting("followup_days")[$i][0],
                        "length" => (int)$this->getProjectSetting("followup_length")[$i][0]
                    ], $commonConfig);
                }
            }
            
            // Load Missed/Cancelled Visit Config
            elseif ( $template == "mcv" ) {
                $indicator = $this->getProjectSetting("mcv_indicator")[$i][0];
                $dateField = $this->getProjectSetting("mcv_date")[$i][0];
                if ( !empty($indicator) && !empty($dateField) ) {
                    $skipEvents = array_map('trim', explode(',',$this->getProjectSetting("mcv_skip_events")[$i][0])); 
                    foreach( REDCap::getEventNames(true) as $eventID => $eventName ) {
                        if ( in_array($eventName, $skipEvents) )
                            continue;
                        $arr = array_merge([
                            "event" => $eventID,
                            "indicator" => $indicator,
                            "apptDate" => $dateField
                        ], $commonConfig);
                        $arr['id'] = $arr['id'].'|'.$eventName;
                        $arr['name'] = $arr['name'].' - '.$eventNameMap[$eventName];
                        $mcvConfig[] = $arr;
                    }
                }
            }
        }
        $this->_callTemplateConfig = [
            "new" => $newEntryConfig,
            "reminder" => $reminderConfig,
            "followup" => $followupConfig,
            "mcv" => $mcvConfig
        ];
        return $this->_callTemplateConfig;
    }
    
    public function loadTabConfig() {
        global $Proj;
        foreach( $this->getProjectSetting("tab_name") as $i => $tab_name) {
            $calls = $this->getProjectSetting("tab_calls_included")[$i];
            $tab_id = str_replace(' ', '_',strtolower($tab_name));
            $tabConfig[$i] = [
               "tab_name" => $tab_name,
               "included_calls" => $calls,
               "tab_id" => $tab_id,
               "fields" => $this->getProjectSetting("tab_field")[$i]
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
                $tabConfig[$i]["fields"][$j] = [
                    "field" => $field,
                    "displayName" => $name,
                    "validation" => $validation,
                    "isFormStatus" => $Proj->isFormStatus($field),
                    "link" => $this->getProjectSetting("tab_field_link")[$i][$j]
                ];
            }
        }
        return [
            'config' => $tabConfig,
            'call2tabMap' => $call2TabMap
        ];
    }
    
    public function getDictionaryLabelFor($key) {
        $label = $this->getDataDictionary("array")[$key]['field_label'];
        if (empty($label)) {
            return $key;
        }
        return $label;
    }
    
    public function getDataDictionary($format = 'array') {
        if(!array_key_exists($format, $this->_dataDictionary)){
            $this->_dataDictionary[$format] = \REDCap::getDataDictionary($format);
        }
        $dictionaryToReturn = $this->_dataDictionary[$format];
        return $dictionaryToReturn;
    }
    
    /////////////////////////////////////////////////
    // Private Functions - Global Passing
    /////////////////////////////////////////////////
    
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
            "static" => [
                "instrument" => $this->instrumentName,
                "instrumentLower" => $this->instrumentLower,
                "instrumentMetadata" => $this->instrumentMeta
            ]
        );
        echo "<script>var ".$this->module_global." = ".json_encode($data).";</script>";
    }
    
    private function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }
    
    private function passArgument($name, $value) {
        echo "<script>".$this->module_global.".".$name." = ".json_encode($value).";</script>";
    }
}