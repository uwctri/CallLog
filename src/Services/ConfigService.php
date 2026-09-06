<?php

namespace UWMadison\CallLog\Services;

use REDCap;
use UWMadison\CallLog\CallMetadataRepository;
use UWMadison\CallLog\CallTemplateType;

class ConfigService
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * Master centralized defaults for all module project settings.
     *
     * @return array
     */
    public function getDefaultSettings(): array
    {
        return [
            'show_record_home_button' => ['1'],
            'show_call_log_instrument' => ['0'],
            'show_metadata_instrument' => ['0'],
            'datetime_format' => ['m/d/Y g:i A'],
            'trigger_save' => [],
            'same_day_mcv_nts' => ['0'],
            'enabled_holidays' => [
                'new_years_day', 'mlk_day', 'memorial_day', 'juneteenth',
                'independence_day', 'labor_day', 'veterans_day', 'thanksgiving',
                'day_after_thanksgiving', 'christmas_eve', 'christmas_day', 'new_years_eve'
            ],
            'custom_holidays_date' => [],
            'custom_holidays_name' => [],
            'call_summary' => [],
            'withdraw_event' => [],
            'withdraw_var' => [],
            'call_id' => [],
            'call_name' => [],
            'call_template' => [],
            'display_name_field' => '',
            'call_expected_duration' => [],
            'hide_after_attempts' => [],
            'new_expire_days' => [],
            'reminder_variable' => [],
            'reminder_days' => [],
            'reminder_include_events' => [],
            'reminder_remove_event' => [],
            'reminder_remove_var' => [],
            'followup_event' => [],
            'followup_date' => [],
            'followup_include_events' => [],
            'followup_days' => [],
            'followup_length' => [],
            'followup_end' => [],
            'followup_auto_remove' => [],
            'mcv_indicator' => [],
            'mcv_date' => [],
            'mcv_include_events' => [],
            'mcv_auto_remove' => [],
            'nts_indicator' => [],
            'nts_date' => [],
            'nts_include_events' => [],
            'nts_skip' => [],
            'nts_window_start_cron' => [],
            'nts_window_days_before' => [],
            'adhoc_reason' => [],
            'visit_indicator' => [],
            'visit_include_events' => [],
            'visit_auto_remove' => [],
            'tab_name' => [],
            'tab_calls_included' => [],
            'tab_order' => [],
            'tab_field' => [[]],
            'tab_field_name' => [[]],
            'tab_field_default' => [[]],
            'tab_field_link' => [[]],
            'tab_field_link_instrument' => [[]],
            'tab_expands_field' => [[]],
            'tab_expands_field_name' => [[]],
            'tab_expands_field_default' => [[]]
        ];
    }

    /**
     * Retrieves raw project settings and merges them with master defaults.
     *
     * @param int $projectId
     * @return array
     */
    public function getRawProjectSettings(int $projectId): array
    {
        $defaults = $this->getDefaultSettings();
        $clean = [];

        foreach ($defaults as $key => $defaultVal) {
            $val = $this->module->getProjectSetting($key, $projectId);
            if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                $val = $defaultVal;
            }
            $clean[$key] = $val;
        }

        return $clean;
    }

    public function isSettingEnabled(int $projectId, string $key, bool $default = false): bool
    {
        $raw = $this->getRawProjectSettings($projectId);
        $val = $raw[$key] ?? null;
        if ($val === null || $val === '' || $val === []) {
            return $default;
        }
        if (is_array($val)) {
            $val = reset($val);
        }
        return $val === '1' || $val === 1 || $val === true || $val === 'true';
    }

    public function saveProjectSettings(int $projectId, array $newSettings): bool
    {
        $allowedKeys = array_keys($this->getDefaultSettings());

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $newSettings)) {
                $val = $newSettings[$key];
                $this->module->setProjectSetting($key, $val, $projectId);
            }
        }

        return true;
    }

    public function getProjectMetadataInfo(int $projectId): array
    {
        $Proj = new \Project($projectId);

        $events = [];
        if (is_array($Proj->events)) {
            foreach ($Proj->events as $armNum => $armDetails) {
                if (isset($armDetails['events']) && is_array($armDetails['events'])) {
                    foreach ($armDetails['events'] as $eventId => $evtDetails) {
                        $events[] = [
                            'id' => (string)$eventId,
                            'name' => $evtDetails['descrip'] ?? "Event {$eventId}",
                            'unique' => $evtDetails['custom_event_label'] ?? "event_{$eventId}"
                        ];
                    }
                }
            }
        }

        if (empty($events) && !empty($Proj->firstEventId)) {
            $events[] = [
                'id' => (string)$Proj->firstEventId,
                'name' => 'Event 1',
                'unique' => 'event_1_arm_1'
            ];
        }

        $instruments = [];
        if (is_array($Proj->forms)) {
            foreach ($Proj->forms as $formName => $formDetails) {
                $instruments[] = [
                    'id' => $formName,
                    'label' => $formDetails['menu'] ?? $formName
                ];
            }
        }

        $dd = [];
        try {
            if (class_exists('\REDCap') && method_exists('\REDCap', 'getDataDictionary')) {
                $dd = \REDCap::getDataDictionary('array', false, [], [], $projectId);
                if (empty($dd)) {
                    $dd = \REDCap::getDataDictionary('array');
                }
            }
        } catch (\Throwable $e) {
            $dd = [];
        }

        $fields = [];
        $dateFields = [];
        if (is_array($Proj->metadata)) {
            foreach ($Proj->metadata as $fieldName => $fieldInfo) {
                $type = strtolower($fieldInfo['element_type'] ?? 'text');
                if ($type === 'descriptive' || $type === 'descriptive_text') {
                    continue;
                }
                $rawLabel = $fieldInfo['element_label'] ?? $fieldName;
                $cleanLabel = trim(strip_tags(html_entity_decode($rawLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                
                $displayText = $fieldName;
                if (!empty($cleanLabel) && strcasecmp($cleanLabel, $fieldName) !== 0) {
                    $strLen = function_exists('mb_strlen') ? mb_strlen($cleanLabel) : strlen($cleanLabel);
                    $subStr = function_exists('mb_substr') ? mb_substr($cleanLabel, 0, 47) : substr($cleanLabel, 0, 47);
                    $truncatedLabel = ($strLen > 50) ? ($subStr . '...') : $cleanLabel;
                    $displayText = "{$fieldName} - {$truncatedLabel}";
                }

                $ddField = $dd[$fieldName] ?? [];
                $valType = strtolower($ddField['text_validation_type_or_show_slider_number'] ?? $fieldInfo['element_validation_type'] ?? '');
                $fieldType = strtolower($ddField['field_type'] ?? $type);

                $isDate = (
                    str_starts_with($valType, 'date') ||
                    str_starts_with($valType, 'datetime') ||
                    str_starts_with($fieldType, 'date') ||
                    str_starts_with($fieldType, 'datetime')
                );

                $fieldData = [
                    'id' => $fieldName,
                    'name' => !empty($cleanLabel) ? $cleanLabel : $fieldName,
                    'label' => $displayText,
                    'type' => $type,
                    'form' => $fieldInfo['form_name'] ?? ($ddField['form_name'] ?? ''),
                    'validation' => $valType,
                    'isDate' => $isDate
                ];

                $fields[] = $fieldData;
                if ($isDate) {
                    $dateFields[] = $fieldData;
                }
            }
        } elseif (!empty($dd)) {
            foreach ($dd as $fieldName => $ddField) {
                $fieldType = strtolower($ddField['field_type'] ?? 'text');
                if ($fieldType === 'descriptive' || $fieldType === 'descriptive_text') {
                    continue;
                }
                $rawLabel = $ddField['field_label'] ?? $fieldName;
                $cleanLabel = trim(strip_tags(html_entity_decode($rawLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                
                $displayText = $fieldName;
                if (!empty($cleanLabel) && strcasecmp($cleanLabel, $fieldName) !== 0) {
                    $strLen = function_exists('mb_strlen') ? mb_strlen($cleanLabel) : strlen($cleanLabel);
                    $subStr = function_exists('mb_substr') ? mb_substr($cleanLabel, 0, 47) : substr($cleanLabel, 0, 47);
                    $truncatedLabel = ($strLen > 50) ? ($subStr . '...') : $cleanLabel;
                    $displayText = "{$fieldName} - {$truncatedLabel}";
                }

                $valType = strtolower($ddField['text_validation_type_or_show_slider_number'] ?? '');
                $isDate = (
                    str_starts_with($valType, 'date') ||
                    str_starts_with($valType, 'datetime') ||
                    str_starts_with($fieldType, 'date') ||
                    str_starts_with($fieldType, 'datetime')
                );

                $fieldData = [
                    'id' => $fieldName,
                    'name' => !empty($cleanLabel) ? $cleanLabel : $fieldName,
                    'label' => $displayText,
                    'type' => $fieldType,
                    'form' => $ddField['form_name'] ?? '',
                    'validation' => $valType,
                    'isDate' => $isDate
                ];

                $fields[] = $fieldData;
                if ($isDate) {
                    $dateFields[] = $fieldData;
                }
            }
        }

        $metadataRepo = new CallMetadataRepository();
        $callStats = $metadataRepo->getCallLogStats($projectId);

        $deployService = new InstrumentDeploymentService();
        $isDeployed = $deployService->isDeployed($projectId);
        $instrumentEventConfig = $deployService->checkInstrumentConfiguration($projectId);

        return [
            'events' => $events,
            'instruments' => $instruments,
            'fields' => $fields,
            'dateFields' => $dateFields,
            'totalCalls' => $callStats['totalCalls'] ?? 0,
            'completedCalls' => $callStats['completedCalls'] ?? 0,
            'totalAttempts' => $callStats['totalAttempts'] ?? 0,
            'uniqueCallers' => $callStats['uniqueCallers'] ?? 0,
            'instrumentsDeployed' => $isDeployed,
            'instrumentEventConfig' => $instrumentEventConfig,
            'defaultHolidayMap' => DateMathService::$defaultHolidayMap,
            'callTemplateOptions' => CallTemplateType::getOptions(),
            'fieldLinkOptions' => [
                'none' => 'None (Plain Text)',
                'home' => 'Record Home Page',
                'call' => 'Call Log Instrument',
                'instrument' => 'Specific Instrument'
            ]
        ];
    }

    public function getCallTemplateConfig(int $projectId): array
    {
        $eventNameMap = $this->getEventNameMap();
        $newEntryConfig = [];
        $reminderConfig = [];
        $followupConfig = [];
        $mcvConfig = [];
        $ntsConfig = [];
        $adhocConfig = [];
        $visitConfig = [];

        $settings = $this->getRawProjectSettings($projectId);
        $templates = $settings["call_template"];

        foreach ($templates as $i => $template) {
            $hide = $settings["hide_after_attempts"][$i] ?? null;
            $duration = $settings["call_expected_duration"][$i] ?? null;
            if (is_array($duration)) $duration = reset($duration);
            $commonConfig = [
                "id" => $settings["call_id"][$i] ?? '',
                "name" => $settings["call_name"][$i] ?? '',
                "hideAfterAttempt" => $hide ? (int)$hide : 9999,
                "callDuration" => ($duration !== null && $duration !== '' && is_numeric($duration)) ? (int)$duration : 30
            ];

            if ($template === "new") {
                $days = intval($settings["new_expire_days"][$i][0] ?? $settings["new_expire_days"][$i] ?? 0);
                $newEntryConfig[] = array_merge(["expire" => $days], $commonConfig);
            } elseif ($template === "reminder") {
                $field = $settings["reminder_variable"][$i][0] ?? $settings["reminder_variable"][$i] ?? '';
                if (empty($field)) continue;
                $rawEvents = $settings["reminder_include_events"][$i][0] ?? $settings["reminder_include_events"][$i] ?? '';
                $includeEvents = array_map('trim', explode(',', $rawEvents));
                foreach ($includeEvents as $eventName) {
                    if (empty($eventName)) continue;
                    $eventId = REDCap::getEventIdFromUniqueEvent($eventName);
                    $arr = array_merge([
                        "event" => $eventId,
                        "field" => $field,
                        "days" => (int)($settings["reminder_days"][$i][0] ?? $settings["reminder_days"][$i] ?? 0),
                        "removeEvent" => $settings["reminder_remove_event"][$i][0] ?? $settings["reminder_remove_event"][$i] ?? '',
                        "removeVar" => $settings["reminder_remove_var"][$i][0] ?? $settings["reminder_remove_var"][$i] ?? ''
                    ], $commonConfig);
                    $arr['id'] .= '|' . $eventName;
                    $arr['name'] .= ' - ' . ($eventNameMap[$eventName] ?? $eventName);
                    $reminderConfig[] = $arr;
                }
            } elseif ($template === "followup") {
                $event = $settings["followup_event"][$i][0] ?? $settings["followup_event"][$i] ?? '';
                $field = $settings["followup_date"][$i][0] ?? $settings["followup_date"][$i] ?? '';
                $days = (int)($settings["followup_days"][$i][0] ?? $settings["followup_days"][$i] ?? 0);
                $length = (int)($settings["followup_length"][$i][0] ?? $settings["followup_length"][$i] ?? 0);
                $end = $settings["followup_end"][$i][0] ?? $settings["followup_end"][$i] ?? '';
                if (!empty($field) && !empty($event)) {
                    $followupConfig[] = array_merge([
                        "event" => $event,
                        "field" => $field,
                        "days" => $days,
                        "length" => $length,
                        "end" => $end
                    ], $commonConfig);
                } elseif (!empty($field)) {
                    $rawEvents = $settings["followup_include_events"][$i][0] ?? $settings["followup_include_events"][$i] ?? '';
                    $includeEvents = array_map('trim', explode(',', $rawEvents));
                    foreach ($includeEvents as $eventName) {
                        if (empty($eventName)) continue;
                        $arr = array_merge([
                            "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                            "field" => $field,
                            "days" => $days,
                            "length" => $length,
                            "end" => $end
                        ], $commonConfig);
                        $arr['id'] .= '|' . $eventName;
                        $arr['name'] .= ' - ' . ($eventNameMap[$eventName] ?? $eventName);
                        $followupConfig[] = $arr;
                    }
                }
            } elseif ($template === "mcv") {
                $indicator = $settings["mcv_indicator"][$i][0] ?? $settings["mcv_indicator"][$i] ?? '';
                $dateField = $settings["mcv_date"][$i][0] ?? $settings["mcv_date"][$i] ?? '';
                if (empty($indicator) || empty($dateField)) continue;
                $rawEvents = $settings["mcv_include_events"][$i][0] ?? $settings["mcv_include_events"][$i] ?? '';
                $includeEvents = array_map('trim', explode(',', $rawEvents));
                foreach ($includeEvents as $eventName) {
                    if (empty($eventName)) continue;
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "indicator" => $indicator,
                        "apptDate" => $dateField,
                    ], $commonConfig);
                    $arr['id'] .= '|' . $eventName;
                    $arr['name'] .= ' - ' . ($eventNameMap[$eventName] ?? $eventName);
                    $mcvConfig[] = $arr;
                }
            } elseif ($template === "nts") {
                $indicator = $settings["nts_indicator"][$i][0] ?? $settings["nts_indicator"][$i] ?? '';
                $dateField = $settings["nts_date"][$i][0] ?? $settings["nts_date"][$i] ?? '';
                $skipField = $settings["nts_skip"][$i][0] ?? $settings["nts_skip"][$i] ?? '';
                $window = $settings["nts_window_start_cron"][$i][0] ?? $settings["nts_window_start_cron"][$i] ?? '';
                $windowDays = $settings["nts_window_days_before"][$i][0] ?? $settings["nts_window_days_before"][$i] ?? 0;
                if (empty($indicator) || empty($dateField)) continue;
                $rawEvents = $settings["nts_include_events"][$i][0] ?? $settings["nts_include_events"][$i] ?? '';
                $includeEvents = array_map('trim', explode(',', $rawEvents));
                foreach ($includeEvents as $eventName) {
                    if (empty($eventName)) continue;
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "indicator" => $indicator,
                        "apptDate" => $dateField,
                        "skip" => $skipField,
                        "window" => $window,
                        "windowDaysBefore" => intval($windowDays)
                    ], $commonConfig);
                    $arr['id'] .= '|' . $eventName;
                    $arr['name'] .= ' - ' . ($eventNameMap[$eventName] ?? $eventName);
                    $ntsConfig[] = $arr;
                }
            } elseif ($template === "adhoc") {
                $reasons = $settings["adhoc_reason"][$i][0] ?? $settings["adhoc_reason"][$i] ?? '';
                if (empty($reasons)) continue;
                $adhocConfig[] = array_merge([
                    "reasons" => $this->explodeCodedValueText($reasons),
                ], $commonConfig);
            } elseif ($template === "visit") {
                $indicator = $settings["visit_indicator"][$i][0] ?? $settings["visit_indicator"][$i] ?? '';
                $autoField = $settings["visit_auto_remove"][$i][0] ?? $settings["visit_auto_remove"][$i] ?? '';
                if (empty($indicator)) continue;
                $rawEvents = $settings["visit_include_events"][$i][0] ?? $settings["visit_include_events"][$i] ?? '';
                $includeEvents = array_map('trim', explode(',', $rawEvents));
                foreach ($includeEvents as $eventName) {
                    if (empty($eventName)) continue;
                    $arr = array_merge([
                        "event" => REDCap::getEventIdFromUniqueEvent($eventName),
                        "indicator" => $indicator,
                        "autoRemove" => $autoField
                    ], $commonConfig);
                    $arr['id'] .= '|' . $eventName;
                    $arr['name'] .= ' - ' . ($eventNameMap[$eventName] ?? $eventName);
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

    public function getAutoRemoveConfig(int $projectId): array
    {
        $settings = $this->getRawProjectSettings($projectId);
        $config = [];
        foreach ($settings["call_template"] as $i => $template) {
            $callId = $settings["call_id"][$i] ?? null;
            if (!$callId) continue;
            if ($template === "mcv") {
                $config[$callId] = $settings["mcv_auto_remove"][$i][0] ?? $settings["mcv_auto_remove"][$i] ?? null;
            } elseif ($template === "visit") {
                $config[$callId] = $settings["visit_auto_remove"][$i][0] ?? $settings["visit_auto_remove"][$i] ?? null;
            } elseif ($template === "followup") {
                $config[$callId] = $settings["followup_auto_remove"][$i][0] ?? $settings["followup_auto_remove"][$i] ?? null;
            }
        }
        return $config;
    }

    public function getTabConfig(int $projectId): array
    {
        global $Proj;
        $allFields = [];
        $call2TabMap = [];
        $tabNameMap = [];
        $tabConfig = [];

        $settings = $this->getRawProjectSettings($projectId);
        $orderMapping = $settings["tab_order"];
        $recordIdField = REDCap::getRecordIdField();
        $recordIdLabel = $this->getFieldLabel($recordIdField);
        $dd = REDCap::getDataDictionary('array');

        $expands = [];
        $expandsFieldList = $settings["tab_expands_field"][0] ?? $settings["tab_expands_field"];
        if (!empty($expandsFieldList)) {
            foreach ($expandsFieldList as $i => $field) {
                if (empty($field)) continue;
                $namesList = $settings["tab_expands_field_name"][0] ?? $settings["tab_expands_field_name"] ?? [];
                $defaultList = $settings["tab_expands_field_default"][0] ?? $settings["tab_expands_field_default"] ?? [];
                $name = $namesList[$i] ?? trim($this->getFieldLabel($field), ":?");
                $validation = $Proj->metadata[$field]["element_validation_type"] ?? "";
                $default = $defaultList[$i] ?? "";
                $expands[] = [
                    "field" => $field,
                    "map" => $this->getDictionaryValuesFor($field, $dd),
                    "displayName" => trim($name) . ": ",
                    "validation" => $validation,
                    "isDate" => strpos($validation, 'date') !== false,
                    "hasTime" => strpos($validation, 'datetime') !== false,
                    "isFormStatus" => isset($Proj) && $Proj->isFormStatus($field),
                    "fieldType" => $Proj->metadata[$field]["element_type"] ?? 'text',
                    "default" => $default,
                    "expanded" => true
                ];
                $allFields[] = $field;
            }
        }

        $callIds = $settings['call_id'] ?? [];
        $callTemplates = $settings['call_template'] ?? [];
        $callIdToTemplate = [];
        if (is_array($callIds) && is_array($callTemplates)) {
            foreach ($callIds as $idx => $cId) {
                $cId = trim((string)$cId);
                if ($cId !== '') {
                    $callIdToTemplate[$cId] = $callTemplates[$idx] ?? '';
                }
            }
        }

        $tabNames = $settings["tab_name"] ?? [];
        $validOrders = is_array($orderMapping) ? array_filter($orderMapping, fn($v) => $v !== null && $v !== '') : [];
        if (count($validOrders) !== count($tabNames)) {
            $orderMapping = range(0, max(0, count($tabNames) - 1));
        }

        $usedTabIds = [];
        foreach ($tabNames as $i => $tabName) {
            $tabOrder = $orderMapping[$i] ?? $i;
            $calls = $settings["tab_calls_included"][$i] ?? '';
            $baseTabId = preg_replace('/_+/', '_', trim(preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', strtolower($tabName))), '_'));
            if (empty($baseTabId)) {
                $baseTabId = "tab_" . ($i + 1);
            }
            $tabId = $baseTabId;
            $suffix = 1;
            while (isset($usedTabIds[$tabId])) {
                $tabId = $baseTabId . '_' . (++$suffix);
            }
            $usedTabIds[$tabId] = true;

            $tabNameMap[$tabId] = $tabName;
            $callsList = is_array($calls) ? $calls : explode(',', (string)$calls);
            $callsArray = array_filter(array_map('trim', $callsList));
            $tabTemplates = [];
            foreach ($callsArray as $call) {
                if (!empty($call)) {
                    if (!isset($call2TabMap[$call])) {
                        $call2TabMap[$call] = [];
                    }
                    if (!in_array($tabId, $call2TabMap[$call], true)) {
                        $call2TabMap[$call][] = $tabId;
                    }
                    if (isset($callIdToTemplate[$call])) {
                        $tabTemplates[] = $callIdToTemplate[$call];
                    }
                }
            }
            $tabTemplates = array_unique($tabTemplates);

            $showVisit = in_array('nts', $tabTemplates, true)
                || in_array('mcv', $tabTemplates, true)
                || in_array('reminder', $tabTemplates, true)
                || in_array('followup', $tabTemplates, true)
                || in_array('visit', $tabTemplates, true);

            $showFollowup = in_array('followup', $tabTemplates, true)
                || (($settings["tab_includes_followup"][$i] ?? '') === '1');

            $showReminder = in_array('reminder', $tabTemplates, true);

            $showMcv = in_array('mcv', $tabTemplates, true)
                || (($settings["tab_includes_mcv"][$i] ?? '') === '1');

            $showAdhoc = in_array('adhoc', $tabTemplates, true)
                || (($settings["tab_includes_adhoc"][$i] ?? '') === '1');

            $showNew = in_array('new', $tabTemplates, true);

            $tabConfig[$tabOrder] = [
                "tab_name" => $tabName,
                "included_calls" => $calls,
                "tab_id" => $tabId,
                "fields" => [],
                "showVisit" => $showVisit,
                "showFollowupWindows" => $showFollowup,
                "showReminderAppt" => $showReminder,
                "showMissedDateTime" => $showMcv,
                "showAdhocDates" => $showAdhoc,
                "showNewExpiration" => $showNew,
                "includedTemplates" => array_values($tabTemplates)
            ];

            $tabFields = [
                [
                    "field" => $recordIdField,
                    "displayName" => "Record ID",
                    "validation" => "",
                    "link" => $settings["tab_link"][$i] ?? "home",
                    "linkedEvent" => $settings["tab_field_link_event"][$i] ?? '',
                    "linkedInstrument" => $settings["tab_field_link_instrument"][$i] ?? '',
                    "expanded" => false
                ]
            ];

            $tabFieldsList = $settings["tab_field"][$i] ?? [];
            if (!empty($tabFieldsList)) {
                foreach ($tabFieldsList as $j => $field) {
                    if (empty($field)) continue;
                    $namesList = $settings["tab_field_name"][$i] ?? [];
                    $defaultList = $settings["tab_field_default"][$i] ?? [];
                    $linkList = $settings["tab_field_link"][$i] ?? [];
                    $instList = $settings["tab_field_link_instrument"][$i] ?? [];

                    $name = $namesList[$j] ?? trim($this->getFieldLabel($field), ":?");
                    $validation = $Proj->metadata[$field]["element_validation_type"] ?? "";
                    $default = $defaultList[$j] ?? "";
                    $link = $linkList[$j] ?? "none";
                    $linkedInst = $instList[$j] ?? "";

                    $tabFields[] = [
                        "field" => $field,
                        "map" => $this->getDictionaryValuesFor($field, $dd),
                        "displayName" => trim($name),
                        "validation" => $validation,
                        "isDate" => strpos($validation, 'date') !== false,
                        "hasTime" => strpos($validation, 'datetime') !== false,
                        "isFormStatus" => isset($Proj) && $Proj->isFormStatus($field),
                        "fieldType" => $Proj->metadata[$field]["element_type"] ?? 'text',
                        "default" => $default,
                        "link" => $link,
                        "linkedInstrument" => $linkedInst,
                        "expanded" => false
                    ];
                    $allFields[] = $field;
                }
            }

            $tabConfig[$tabOrder]["fields"] = array_merge($tabFields, $expands);
            $tabConfig[$tabOrder]["expands"] = $expands;
        }

        ksort($tabConfig);

        $displayNameField = $settings['display_name_field'] ?? '';
        if (is_array($displayNameField)) $displayNameField = reset($displayNameField);

        return [
            "allFields" => array_values(array_unique($allFields)),
            "displayNameField" => (string)$displayNameField,
            "call2TabMap" => $call2TabMap,
            "call2tabMap" => $call2TabMap,
            "tabNameMap" => $tabNameMap,
            "config" => array_values($tabConfig)
        ];
    }

    public function getAdhocTemplateConfig(int $projectId): array
    {
        $settings = $this->getRawProjectSettings($projectId);
        $config = [];
        foreach ($settings["call_template"] as $i => $template) {
            if ($template === "adhoc") {
                $callId = $settings["call_id"][$i] ?? null;
                $reasons = $settings["adhoc_reason"][$i][0] ?? $settings["adhoc_reason"][$i] ?? '';
                if ($callId && !empty($reasons)) {
                    $config[$callId] = [
                        "name" => $settings["call_name"][$i] ?? '',
                        "reasons" => $this->explodeCodedValueText($reasons)
                    ];
                }
            }
        }
        return $config;
    }



    public function getEventNameMap(): array
    {
        $events = REDCap::getEventNames(false, true);
        return is_array($events) ? array_flip($events) : [];
    }

    private function getFieldLabel(string $fieldName): string
    {
        global $Proj;
        return $Proj->metadata[$fieldName]["element_label"] ?? $fieldName;
    }

    private function getDictionaryValuesFor(string $fieldName, array $dd): array
    {
        $fieldInfo = $dd[$fieldName] ?? null;
        if (!$fieldInfo) return [];

        $type = $fieldInfo["element_type"] ?? "";
        if (!in_array($type, ["select", "radio", "checkbox", "yesno", "truefalse"], true)) {
            return [];
        }

        if ($type === "yesno") {
            return ["1" => "Yes", "0" => "No"];
        }
        if ($type === "truefalse") {
            return ["1" => "True", "0" => "False"];
        }

        $enumStr = $fieldInfo["select_choices_or_calculations"] ?? "";
        return $this->explodeCodedValueText($enumStr);
    }

    private function explodeCodedValueText(string $str): array
    {
        $map = [];
        $lines = preg_split('/\r\n|\r|\n|\\\\n|\|/', $str);
        foreach ($lines as $line) {
            $parts = explode(",", $line, 2);
            if (count($parts) === 2) {
                $map[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $map;
    }

    public function getWithdrawConfig(int $projectId): array
    {
        $raw = $this->getRawProjectSettings($projectId);
        $events = is_array($raw['withdraw_event']) ? $raw['withdraw_event'] : (array)($raw['withdraw_event'] ?? []);
        $vars = is_array($raw['withdraw_var']) ? $raw['withdraw_var'] : (array)($raw['withdraw_var'] ?? []);

        $rules = [];
        foreach ($vars as $i => $var) {
            if (empty($var)) continue;
            $rules[] = [
                'event' => $events[$i] ?? '',
                'var' => $var
            ];
        }
        return $rules;
    }
}
