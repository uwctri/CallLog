<?php

namespace UWMadison\CallLog;

use Redcap;
use Piping;

trait Configuration
{
    private function initGlobal()
    {
        $this->initializeJavascriptModuleObject();
        $call_event = $this->getEventOfInstrument($this->instrument);
        $meta_event = $this->getEventOfInstrument($this->instrumentMeta);
        $username = $this->getUser()->getUsername();
        $data = json_encode([
            "eventNameMap" => $this->getEventNameMap(),
            "prefix" => $this->getPrefix(),
            "user" => $username,
            "userNameMap" => $this->getUserNameMap(),
            "format" => $this->getUserDateFormat($username),
            "static" => [
                "instrument" => $this->instrument,
                "instrumentEvent" => $call_event,
                "record_id" => REDCap::getRecordIdField()
            ],
            "configError" => !($call_event && $meta_event)
        ]);
        echo "<script>Object.assign({$this->getJavascriptModuleObjectName()}, {$data});</script>";
    }

    private function getCallTemplateConfig()
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
                if (!empty($field) && !empty($event)) {
                    $followupConfig[] = array_merge([
                        "event" => $event,
                        "field" => $field,
                        "days" => $days,
                        "length" => $length,
                        "end" => $end
                    ], $commonConfig);
                } elseif (!empty($field)) {
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
                $window_days = $settings["nts_window_days_before"][$i][0];
                if (empty($indicator) || empty($dateField)) continue;
                $includeEvents = array_map('trim', explode(',', $settings["nts_include_events"][$i][0]));
                foreach ($includeEvents as $eventName) {
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "indicator" => $indicator,
                        "apptDate" => $dateField,
                        "skip" => $skipField,
                        "window" => $window,
                        "windowDaysBefore" => intval($window_days)
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

    private function getAutoRemoveConfig()
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

    private function getTabConfig()
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

            // Grab settings for the tab
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

    private function getWithdrawConfig()
    {
        return [
            'event' => $this->getProjectSetting("withdraw_event"),
            'var' => $this->getProjectSetting("withdraw_var"),
            'tmp' => [
                'event' => $this->getProjectSetting("withdraw_tmp_event"),
                'var' => $this->getProjectSetting("withdraw_tmp_var")
            ]
        ];
    }

    private function getReportConfig($excludeWithdrawn = true)
    {
        // Constants for data pull
        $project_id = $_GET['pid']; // TODO we shouldn't pull this
        $metaEvent = $this->getEventOfInstrument($this->instrumentMeta);
        $report_fields = $this->getProjectSetting('report_field')[0];
        $report_names = $this->getProjectSetting('report_field_name')[0];
        $withdraw_config = $this->getWithdrawConfig();

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

    private function getBadPhoneConfig()
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

    private function getAdhocTemplateConfig()
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
}
