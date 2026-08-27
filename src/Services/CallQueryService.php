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
        $rawEventNames = REDCap::getEventNames();
        $eventNameToId = is_array($rawEventNames) ? array_flip($rawEventNames) : [];

        $packagedCallData = [];
        $alwaysShowCallbackCol = false;
        $today = date('Y-m-d');

        foreach ($tabs['config'] as $tab) {
            $packagedCallData[$tab["tab_id"]] = [];
        }

        $fields = array_merge(
            [
                REDCap::getRecordIdField(),
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

        foreach ($dataLoad as $record => $recordData) {
            $rawMeta = $recordData[$metaEvent]["call_metadata"] ?? '';
            if (empty($rawMeta)) continue;

            $meta = json_decode($rawMeta, true);
            if (!is_array($meta)) continue;

            foreach ($meta as $callID => $call) {
                $fullCallID = $callID;
                [$baseCallID, $callIDEvent] = array_pad(array_filter(explode('|', $callID)), 2, "");

                if (!empty($call['complete']) || (substr($baseCallID, 0, 1) === '_') || empty($tabs['call2tabMap'][$baseCallID])) {
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

                $cbReq = $instanceData['call_requested_callback'][1] ?? '0';
                $cbDate = $instanceData['call_callback_date'] ?? '';

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

                $callStartedTime = $call['callStarted'] ?? '';
                $instanceData['_callStarted'] = $callStartedTime && (strtotime($callStartedTime) > strtotime('-30 minutes'));

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

                if (($call['template'] ?? '') === 'followup') {
                    $instanceData['_windowLower'] = $call['start'] ?? '';
                    $instanceData['_windowUpper'] = $call['end'] ?? '';
                }

                if (($call['template'] ?? '') === 'mcv') {
                    $instanceData['_appt_dt'] = $call['appt'] ?? '';
                }

                if (($call['template'] ?? '') === 'adhoc') {
                    $instanceData['_adhocReason'] = $adhoc[$baseCallID]['reasons'][$call['reason']] ?? '';
                    $instanceData['_adhocContactOn'] = $call['contactOn'] ?? '';
                    $instanceData['_futureAdhoc'] = ($call['start'] ?? '') > $today;
                    $notes = !empty($call['initNotes']) ? $call['initNotes'] : "No Notes Taken";
                    if (!empty($call['reporter'])) {
                        $instanceData['_callNotes'] .= "{$call['reported']}||{$call['reporter']}||&nbsp;||{$notes}|||";
                    }
                }

                $instanceData['_call_id'] = $fullCallID;
                $targetTab = $tabs['call2tabMap'][$baseCallID] ?? null;

                if ($targetTab && isset($packagedCallData[$targetTab])) {
                    $packagedCallData[$targetTab][] = $instanceData;
                }
            }
        }

        return [
            "data" => $packagedCallData,
            "showCallback" => $alwaysShowCallbackCol
        ];
    }
}
