<?php

namespace UWMadison\CallLog;

use Redcap;

trait CallLogic
{
    public function metadataNewEntry($project_id, $record, &$metadata, $config)
    {
        // Can't be a new call if metadata already exists
        $changeOccured = false;
        if (!empty($metadata)) return false;
        foreach ($config as $callConfig) {
            // Don't re-create call
            if (!empty($metadata[$callConfig['id']])) continue;
            $metadata[$callConfig['id']] = [
                "template" => 'new',
                "event_id" => '',
                "name" => $callConfig['name'],
                "load" => date("Y-m-d H:i"),
                "instances" => [],
                "voiceMails" => 0,
                "expire" => $callConfig['expire'],
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                "complete" => false
            ];
            $changeOccured = true;
        }
        return $changeOccured;
    }

    public function metadataFollowup($project_id, $record, &$metadata, $config)
    {
        $changeOccured = false;
        foreach ($config as $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['field'], $callConfig['end']])[$record];
            if (!empty($metadata[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == "") {
                //Anchor appt was removed, get rid of followup call too.
                unset($metadata[$callConfig['id']]);
                $changeOccured = true;
            } elseif (empty($metadata[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "") {
                // Anchor is set and the meta doesn't have the call id in it yet
                $start = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $callConfig['days'] . ' days'));
                $end = $data[$callConfig['event']][$callConfig['end']];
                if (empty($end)) {
                    $end = $callConfig['days'] + $callConfig['length'];
                    $end = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $end . ' days'));
                }
                $metadata[$callConfig['id']] = [
                    "start" => $start,
                    "end" => $end,
                    "template" => 'followup',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
                $changeOccured = true;
            } elseif (!empty($metadata[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "") {
                // Update the start/end dates if the call exists and the anchor isn't blank 
                $start = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $callConfig['days'] . ' days'));
                $end = $data[$callConfig['event']][$callConfig['end']];
                if (empty($end)) {
                    $end = $callConfig['days'] + $callConfig['length'];
                    $end = date('Y-m-d', strtotime($data[$callConfig['event']][$callConfig['field']] . ' +' . $end . ' days'));
                }
                if (($metadata[$callConfig['id']]['start'] != $start) || ($metadata[$callConfig['id']]['end'] != $end)) {
                    $metadata[$callConfig['id']]['start'] = $start;
                    $metadata[$callConfig['id']]['end'] = $end;
                    $changeOccured = true;
                }
            }
        }
        return $changeOccured;
    }

    public function metadataReminder($project_id, $record, &$metadata, $config)
    {
        $changeOccured = false;
        $today = date('Y-m-d');
        foreach ($config as $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['field'], $callConfig['removeVar']])[$record];
            if (
                !empty($metadata[$callConfig['id']]) && count($metadata[$callConfig['id']]['instances']) == 0 &&
                $data[$callConfig['removeEvent']][$callConfig['removeVar']]
            ) {
                // Alt flag was set and we haven't recorded calls. Delete the metadata
                unset($metadata[$callConfig['id']]);
                $changeOccured = true;
                continue;
            }
            if ($data[$callConfig['removeEvent']][$callConfig['removeVar']]) continue;
            $newStart = $this->dateMath($data[$callConfig['event']][$callConfig['field']], '-', $callConfig['days']);
            $newEnd = $this->dateMath($data[$callConfig['event']][$callConfig['field']], '+', $callConfig['days'] == 0 ? 365 : 0);
            if (
                !empty($metadata[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == ""
                && count($metadata[$callConfig['id']]['instances']) == 0
            ) {
                // Scheduled appt was removed and no call was made, get rid of reminder call too.
                unset($metadata[$callConfig['id']]);
                $changeOccured = true;
            } elseif (!empty($metadata[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] == "") {
                // Scheduled appt was removed, but a call was made, mark the reminder as complete
                $metadata[$callConfig['id']]["complete"] = true;
                $metadata[$callConfig['id']]["completedBy"] = "REDCap";
                $this->projectLog("Reminder call {$callConfig['id']} marked as complete, appointment was removed.");
                $changeOccured = true;
            } elseif (!empty($metadata[$callConfig['id']]) && ($data[$callConfig['event']][$callConfig['field']] <= $today)) {
                // Appt is today, autocomplete the call so it stops showing up places, we might double set but it doesn't matter
                $metadata[$callConfig['id']]['complete'] = true;
                $metadata[$callConfig['id']]["completedBy"] = "REDCap";
                $this->projectLog("Reminder call {$callConfig['id']} marked as complete, appointment is today.");
                $changeOccured = true;
            } elseif (
                !empty($metadata[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "" &&
                ($metadata[$callConfig['id']]['start'] != $newStart || $metadata[$callConfig['id']]['end'] != $newEnd)
            ) {
                // Scheduled appt exists, the meta has the call id, but the dates don't match (re-shchedule occured)
                $metadata[$callConfig['id']]['complete'] = false;
                $metadata[$callConfig['id']]['start'] = $newStart;
                $metadata[$callConfig['id']]['end'] = $newEnd;
                $this->projectLog("Reminder call {$callConfig['id']} marked as incomplete, appointment was rescheduled.");
                $changeOccured = true;
            } elseif (empty($metadata[$callConfig['id']]) && $data[$callConfig['event']][$callConfig['field']] != "") {
                // Scheduled appt exists and the meta doesn't have the call id in it yet
                $metadata[$callConfig['id']] = [
                    "start" => $newStart,
                    "end" => $newEnd,
                    "template" => 'reminder',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
                $changeOccured = true;
            }
        }
        return $changeOccured;
    }

    public function metadataMissedCancelled($project_id, $record, &$metadata, $config)
    {
        $changeOccured = false;
        foreach ($config as $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator']])[$record][$callConfig['event']];
            $idExact = $callConfig['id'] . '||' . $data[$callConfig['apptDate']];
            if (empty($metadata[$idExact]) && !empty($data[$callConfig['apptDate']]) && !empty($data[$callConfig['indicator']])) {
                // Appt is set, Indicator is set, and metadata is missing, write it.
                $metadata[$idExact] = [
                    "appt" => $data[$callConfig['apptDate']],
                    "template" => 'mcv',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
                $changeOccured = true;
            } elseif (!empty($metadata[$idExact]) && !empty($data[$callConfig['apptDate']]) && empty($data[$callConfig['indicator']])) {
                // The visit has been reschedueld for the exact previous time, or maybe user error
                // Previously we would usent those calls with 0 instances, but this leads to an issue if a mcv is reschedueld on the first try
                $metadata[$idExact]['complete'] = true;
                $metadata[$idExact]["completedBy"] = "REDCap";
                $this->projectLog("Missed/Cancelled call {$idExact} marked as complete, appointment was rescheduled.");
                $changeOccured = true;
            }

            // Search for similar IDs and complete/remove them. We should only have 1 MCV call per event active on the call log
            foreach ($metadata as $callID => $callData) {
                if ($callID == $idExact || $callData['complete'] || $callData['template'] != "mcv" || $callData['event'] != $callConfig['event'])
                    continue;
                if (count($callData["instances"]) == 0) {
                    unset($metadata[$callID]);
                    $changeOccured = true;
                    continue;
                }
                $metadata[$callID]['complete'] = true;
                $metadata[$callID]['completedBy'] = "REDCap";
                $this->projectLog("Missed/Cancelled call {$callID} marked as complete, call appears to be a duplicate.");
            }
        }
        return $changeOccured;
    }


    public function metadataNeedToSchedule($project_id, $record, &$metadata, $config)
    {
        global $Proj;
        $changeOccured = false;
        $orderedEvents = array_combine(array_map(function ($x) {
            return $x['day_offset'];
        }, $Proj->eventInfo), array_keys($Proj->eventInfo));
        $callLogEvent = $this->getEventOfInstrument($this->instrument);
        $metadataEvent = $this->getEventOfInstrument($this->instrumentMeta);
        foreach ($config as $i => $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, [$callConfig['apptDate'], $callConfig['indicator'], $callConfig['skip']])[$record];
            $prevEvent = $orderedEvents[array_search($callConfig['event'], $orderedEvents) - 1];
            // If previous indicator is set (i.e. it was attended) and current event's appt_date is blank, and its not attended then set need to schedule. Also check that skip is either not configured or that it is not-truthy (i.e. 0 or empty).
            if (
                empty($metadata[$callConfig['id']]) && !empty($data[$prevEvent][$callConfig['indicator']]) && empty($data[$callConfig['event']][$callConfig['apptDate']]) && empty($data[$callConfig['event']][$callConfig['indicator']]) &&
                (empty($callConfig['skip']) || (!$data[$callConfig['event']][$callConfig['skip']] && !$data[$prevEvent][$callConfig['skip']] && !$data[$callLogEvent][$callConfig['skip']] && !$data[$metadataEvent][$callConfig['skip']]))
            ) {
                $metadata[$callConfig['id']] = [
                    "template" => 'nts',
                    "event_id" => $callConfig['event'],
                    "name" => $callConfig['name'],
                    "instances" => [],
                    "voiceMails" => 0,
                    "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                    "complete" => false
                ];
                $changeOccured = true;
            } elseif (!empty($metadata[$callConfig['id']]) && !empty($data[$callConfig['event']][$callConfig['apptDate']])) {
                $metadata[$callConfig['id']]['complete'] = true;
                $metadata[$callConfig['id']]['completedBy'] = "REDCap";
                $this->projectLog("Need to Schedue call {$callConfig['id']} marked as complete, appointment was reschedueld.");
                $changeOccured = true;
            }
        }
        return $changeOccured;
    }

    public function metadataPhoneVisit($project_id, $record, &$metadata, $config)
    {
        $changeOccured = false;
        foreach ($config as $i => $callConfig) {
            $data = REDCap::getData($project_id, 'array', $record, $callConfig['indicator'])[$record];
            if (!empty($metadata[$callConfig['id']]) || empty($data[$callConfig['event']][$callConfig['indicator']])) continue;
            $metadata[$callConfig['id']] = [
                "template" => 'visit',
                "event_id" => $callConfig['event'],
                "end" => $data[$callConfig['event']][$callConfig['autoRemove']],
                "name" => $callConfig['name'],
                "instances" => [],
                "voiceMails" => 0,
                "hideAfterAttempt" => $callConfig['hideAfterAttempt'],
                "complete" => false
            ];
            $changeOccured = true;
        }
        return $changeOccured;
    }

    public function metadataUpdateCommon($project_id, $record, &$metadata)
    {
        if (empty($metadata)) return false; // We don't make the 1st metadata entry here.
        $data = $this->getAllCallData($project_id, $record);
        $instance = end(array_keys($data));
        $data = end($data); // get the data of the newest instance only
        $id = $data['call_id'];
        if (in_array($instance, $metadata[$id]["instances"])) return false;
        $metadata[$id]["instances"][] = $instance;
        if ($data['call_left_message'][1] == '1')
            $metadata[$id]["voiceMails"]++;
        if ($data['call_outcome'] == '1') {
            $metadata[$id]['complete'] = true;
            $metadata[$id]['completedBy'] = $this->getUser()->getUsername();
        }
        $metadata[$id]['callStarted'] = '';
        return true;
    }
}
