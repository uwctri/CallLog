<?php

namespace UWMadison\CallLog\Services;

use REDCap;
use UWMadison\CallLog\CallMetadataRepository;
use UWMadison\CallLog\CallTemplateType;
use UWMadison\CallLog\CallItemDTO;

class CallQueryService
{
    private $module;
    private $configService;
    private $metadataRepo;

    public function __construct($module, ConfigService $configService, $metadataRepo)
    {
        $this->module = $module;
        $this->configService = $configService;
        $this->metadataRepo = $metadataRepo;
    }

    public function getCallListData(int $projectId): array
    {
        $callEvent = $this->metadataRepo->getEventOfInstrument($projectId, "call_log");
        $metaEvent = $this->metadataRepo->getEventOfInstrument($projectId, "call_log_metadata");
        $withdrawRules = $this->configService->getWithdrawConfig($projectId);
        $withdrawVars = array_filter(array_column($withdrawRules, 'var'));
        $autoRemoveConfig = $this->configService->getAutoRemoveConfig($projectId);
        $dayOf = $this->module->getProjectSetting('same_day_mcv_nts', $projectId);
        $tabs = $this->configService->getTabConfig($projectId);
        $call2TabMap = $tabs['call2TabMap'] ?? $tabs['call2tabMap'] ?? [];
        $adhocConfig = $this->configService->getAdhocTemplateConfig($projectId);
        $recordIdField = REDCap::getRecordIdField();
        $rawEventNames = REDCap::getEventNames();
        $eventNameToId = is_array($rawEventNames) ? array_flip($rawEventNames) : [];
        $displayNameField = $tabs['displayNameField'] ?? '';

        global $Proj;
        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new \Project($projectId);
        }
        $eventDisplayNames = [];
        if (isset($Proj->eventInfo) && is_array($Proj->eventInfo)) {
            foreach ($Proj->eventInfo as $eid => $info) {
                $disp = !empty($info['name_ext']) ? $info['name_ext'] : (!empty($info['name']) ? $info['name'] : (!empty($info['descrip']) ? $info['descrip'] : (string)$eid));
                $eventDisplayNames[(string)$eid] = $disp;
                if (!empty($info['unique_event_name'])) {
                    $eventDisplayNames[$info['unique_event_name']] = $disp;
                }
            }
        }

        $settings = $this->configService->getRawProjectSettings($projectId);
        $callIds = $settings['call_id'] ?? [];
        $callDurations = $settings['call_expected_duration'] ?? [];
        $callIdToDuration = [];
        foreach ($callIds as $i => $cid) {
            if (!empty($cid)) {
                $d = $callDurations[$i] ?? 30;
                if (is_array($d)) $d = reset($d);
                $callIdToDuration[$cid] = ($d !== null && $d !== '' && is_numeric($d)) ? (int)$d : 30;
            }
        }

        $tabNames = [];
        $packagedCallData = [];
        $alwaysShowCallbackCol = false;
        $today = date('Y-m-d');

        foreach ($tabs['config'] as $tab) {
            $packagedCallData[$tab["tab_id"]] = [];
            $tabNames[$tab["tab_id"]] = $tab["tab_name"] ?? $tab["tab_id"];
        }

        $fields = array_merge(
            [
                $recordIdField,
                "call_metadata",
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
            $withdrawVars,
            array_values($autoRemoveConfig),
            $tabs['allFields']
        );

        if (!empty($displayNameField)) {
            $fields[] = $displayNameField;
        }

        $fields = array_values(array_filter($fields, fn($f) => is_string($f) && !empty($f)));

        $userId = defined('USERID') ? USERID : null;
        $userRights = $userId ? REDCap::getUserRights($userId) : [];
        $groupId = $userRights[$userId]['group_id'] ?? null;

        $dataLoad = REDCap::getData($projectId, 'array', null, $fields, null, $groupId);
        if (empty($dataLoad)) {
            return [
                "data" => $packagedCallData,
                "showCallback" => false
            ];
        }

        $stagedRows = [];
        $recordTabs = [];

        foreach ($dataLoad as $record => $recordData) {
            $rawMeta = $recordData[$metaEvent]["call_metadata"] ?? '';
            if (empty($rawMeta)) continue;

            $meta = json_decode($rawMeta, true);
            if (!is_array($meta)) continue;

            $participantName = '';
            if (!empty($displayNameField)) {
                foreach ($recordData as $evId => $evData) {
                    if ($evId === 'repeat_instances') continue;
                    if (is_array($evData) && isset($evData[$displayNameField]) && $evData[$displayNameField] !== '') {
                        $participantName = (string)$evData[$displayNameField];
                        break;
                    }
                }
            }

            foreach ($meta as $callID => $call) {
                $fullCallID = $callID;
                [$baseCallID, $callIDEvent] = array_pad(array_values(array_filter(explode('|', $callID))), 2, "");

                $targetTabs = $call2TabMap[$baseCallID] ?? [];
                if (!is_array($targetTabs)) {
                    $targetTabs = !empty($targetTabs) ? [$targetTabs] : [];
                }

                if (!empty($call['complete']) || (substr($baseCallID, 0, 1) === '_') || empty($targetTabs)) {
                    continue;
                }

                $templateVal = $call['template'] ?? '';

                if (in_array($templateVal, [CallTemplateType::REMINDER->value, CallTemplateType::FOLLOWUP->value], true) && !empty($call['start']) && ($call['start'] > $today)) {
                    continue;
                }

                if ($templateVal === CallTemplateType::REMINDER->value && !empty($call['end']) && ($call['end'] <= $today)) {
                    continue;
                }

                if ($templateVal === CallTemplateType::FOLLOWUP->value && ($autoRemoveConfig[$baseCallID] ?? false) && !empty($call['end']) && ($call['end'] < $today)) {
                    continue;
                }

                if ($templateVal === CallTemplateType::NEW->value && !empty($call['expire']) && (date('Y-m-d', strtotime("{$call['load']} +{$call['expire']} days")) < $today)) {
                    continue;
                }

                if ($templateVal === CallTemplateType::MCV->value && (explode(' ', $call['appt'] ?? '')[0] === $today) && !$dayOf) {
                    continue;
                }

                if ($templateVal === CallTemplateType::NTS->value && (($call['created'] ?? '') === $today) && !$dayOf) {
                    continue;
                }

                $instances = $call['instances'] ?? [];
                $lastInstance = end($instances);
                $instanceData = $recordData['repeat_instances'][$callEvent]["call_log"][$lastInstance] ?? [];
                $instanceEventData = $recordData[$call['event_id'] ?? ''] ?? [];

                $instanceData = array_merge(
                    array_filter($instanceEventData, fn($v) => $v !== '' && $v !== null),
                    array_filter($recordData[$callEvent] ?? [], fn($v) => $v !== '' && $v !== null),
                    array_filter($instanceData, fn($v) => $v !== '' && $v !== null)
                );

                $instanceData['_record_id'] = (string)$record;
                $instanceData[$recordIdField] = (string)$record;
                $instanceData['_instance'] = $lastInstance ? (int)$lastInstance : 1;
                $instanceData['_event_id'] = $callEvent ? (string)$callEvent : '';
                $instanceData['_participantName'] = $participantName;
                if (!empty($displayNameField)) {
                    $instanceData[$displayNameField] = $participantName;
                }

                $targetEvent = (string)($call['event_id'] ?? $callIDEvent ?? '');
                $visitName = $eventDisplayNames[$targetEvent] ?? '';
                if (empty($visitName) && !empty($callIDEvent)) {
                    $visitName = $eventDisplayNames[$callIDEvent] ?? $callIDEvent;
                }
                if (empty($visitName)) {
                    $visitName = $targetEvent;
                }
                $instanceData['_visitName'] = $visitName;

                $cbReq = $instanceData['call_requested_callback'][1] ?? '0';
                $cbDate = $instanceData['call_callback_date'] ?? '';

                $instanceData['_callbackRequestor'] = $instanceData['call_callback_requested_by'] ?? $call['callbackRequestor'] ?? '';
                $instanceData['_call_date'] = $cbDate ?: ($call['start'] ?? $call['appt'] ?? $call['load'] ?? $call['created'] ?? '');

                $instanceData['_callbackNotToday'] = ($cbReq === '1' && $cbDate > $today);
                $instanceData['_callbackToday'] = ($cbReq === '1' && $cbDate <= $today);

                if (!$instanceData['_callbackToday']) {
                    $autoField = $autoRemoveConfig[$baseCallID] ?? null;
                    if (($call['template'] ?? '') === 'mcv' && $autoField && !empty($instanceData[$autoField]) && ($instanceData[$autoField] < $today)) {
                        continue;
                    }
                    if (($call['template'] ?? '') === 'visit' && $autoField && !empty($instanceData[$autoField]) && ($instanceData[$autoField] < $today)) {
                        continue;
                    }

                    $isWithdrawn = false;
                    foreach ($withdrawRules as $wRule) {
                        $wEvent = $wRule['event'];
                        $wVar = $wRule['var'];
                        if (!empty($wVar)) {
                            if (!empty($wEvent)) {
                                if (!empty($recordData[$wEvent][$wVar])) {
                                    $isWithdrawn = true;
                                    break;
                                }
                            } else {
                                foreach ($recordData as $evData) {
                                    if (is_array($evData) && !empty($evData[$wVar])) {
                                        $isWithdrawn = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                    if ($isWithdrawn) {
                        continue;
                    }
                }

                $alwaysShowCallbackCol = $alwaysShowCallbackCol || ($cbReq === '1' && $cbDate <= $today);

                $callDuration = $callIdToDuration[$baseCallID] ?? 30;
                $callStartedTime = $call['callStarted'] ?? '';
                $isOngoing = false;
                if (!empty($callStartedTime)) {
                    $startedTs = strtotime($callStartedTime);
                    if ($startedTs !== false && (time() - $startedTs) <= ($callDuration * 60)) {
                        $isOngoing = true;
                    }
                }
                $instanceData['_callStarted'] = $isOngoing;
                $instanceData['_isCallStarted'] = $isOngoing;
                $instanceData['_callStartedTime'] = $callStartedTime;
                $instanceData['_callStartedBy'] = $call['callStartedBy'] ?? '';
                $instanceData['_callDuration'] = $callDuration;

                $instanceData['_callGenerated'] = $call['created'] ?? $call['load'] ?? $call['reported'] ?? '';

                $noCallsToday = $call['noCallsToday'] ?? [];
                if (!is_array($noCallsToday)) $noCallsToday = [$noCallsToday];
                $instanceData['_noCallsToday'] = in_array($today, $noCallsToday, true);

                $attempts = ($recordData[$callEvent]['call_open_date'] ?? '') === $today ? 1 : 0;
                $instanceData['_callNotes'] = "";

                foreach (array_reverse($instances) as $inst) {
                    $itterData = $recordData['repeat_instances'][$callEvent]["call_log"][$inst] ?? [];
                    $leftMsg = ($itterData['call_left_message'][1] ?? '') === "1" ? '<b>Left Message</b>' : '';
                    $setCB = ($itterData['call_requested_callback'][1] ?? '') === "1" ? 'Set Callback' : '';
                    $text = $leftMsg && $setCB ? $leftMsg . " & " . $setCB : $leftMsg . $setCB . '&nbsp;';
                    $notes = !empty($itterData['call_notes']) ? $itterData['call_notes'] : 'none';
                    $openDt = $itterData['call_open_datetime'] ?? '';
                    $openUser = $itterData['call_open_user_full_name'] ?? '';
                    $instanceData['_callNotes'] .= "{$openDt}||{$openUser}||{$text}||{$notes}|||";

                    if (($itterData['call_open_date'] ?? '') === $today) {
                        $attempts++;
                    }
                }

                $instanceData['_atMaxAttempts'] = ($call['hideAfterAttempt'] ?? 9999) <= $attempts;
                $instanceData['call_attempt'] = count($instances);

                $instanceData['_nextInstance'] = 1;
                $repeatLogs = $recordData['repeat_instances'][$callEvent]["call_log"] ?? [];
                if (!empty($repeatLogs)) {
                    $lastInstKey = array_key_last($repeatLogs);
                    $instanceData['_nextInstance'] = ($lastInstKey !== null ? (int)$lastInstKey : 0) + 1;
                } elseif (!empty($recordData[$callEvent]['call_template'])) {
                    $instanceData['_nextInstance'] = 2;
                }

                $instanceData['_event'] = $eventNameToId[$callIDEvent] ?? '';
                $instanceData['call_event_name'] = $callIDEvent;

                if (in_array(($call['template'] ?? ''), ['followup', 'reminder'], true)) {
                    $instanceData['_windowLower'] = $call['start'] ?? '';
                    $instanceData['_windowUpper'] = $call['end'] ?? '';
                }

                if (($call['template'] ?? '') === 'new') {
                    $loadDate = $call['load'] ?? '';
                    $expireDays = isset($call['expire']) && $call['expire'] !== '' ? (int)$call['expire'] : null;
                    if (!empty($loadDate) && $expireDays !== null) {
                        $expireTs = strtotime("{$loadDate} +{$expireDays} days");
                        $instanceData['_expireDate'] = date('Y-m-d', $expireTs);
                        $instanceData['_daysRemaining'] = (int)round((strtotime(date('Y-m-d', $expireTs)) - strtotime($today)) / 86400);
                    } else {
                        $instanceData['_expireDate'] = '';
                        $instanceData['_daysRemaining'] = null;
                    }
                }

                if (in_array(($call['template'] ?? ''), ['mcv', 'reminder'], true)) {
                    $instanceData['_appt_dt'] = $call['appt'] ?? '';
                }

                if (($call['template'] ?? '') === 'adhoc') {
                    $instanceData['_adhocReason'] = $adhocConfig[$baseCallID]['reasons'][$call['reason']] ?? '';
                    $instanceData['_adhocContactOn'] = $call['contactOn'] ?? '';
                    $instanceData['_futureAdhoc'] = ($call['start'] ?? '') > $today;
                    $notes = !empty($call['initNotes']) ? $call['initNotes'] : "No Notes Taken";
                    if (!empty($call['reporter'])) {
                        $instanceData['_callNotes'] .= "{$call['reported']}||{$call['reporter']}||&nbsp;||{$notes}|||";
                    }
                }

                $instanceData['_call_id'] = $fullCallID;

                $stagedRows[] = [
                    'row' => $instanceData,
                    'tabs' => $targetTabs,
                    'record' => (string)$record
                ];

                foreach ($targetTabs as $tTab) {
                    $recordTabs[(string)$record][$tTab] = $tabNames[$tTab] ?? $tTab;
                }
            }
        }

        foreach ($stagedRows as $staged) {
            $rec = $staged['record'];
            $allRecordTabs = $recordTabs[$rec] ?? [];
            $isMulti = count($allRecordTabs) > 1;
            $rowData = $staged['row'];
            $rowData['_onMultipleTabs'] = $isMulti;

            foreach ($staged['tabs'] as $targetTab) {
                if (isset($packagedCallData[$targetTab])) {
                    $tabRow = $rowData;
                    $other = [];
                    foreach ($allRecordTabs as $tId => $tName) {
                        if ($tId !== $targetTab) {
                            $other[] = $tName;
                        }
                    }
                    $tabRow['_otherTabs'] = $other;
                    $packagedCallData[$targetTab][] = $tabRow;
                }
            }
        }


        return [
            "data" => $packagedCallData,
            "showCallback" => $alwaysShowCallbackCol
        ];
    }
}
