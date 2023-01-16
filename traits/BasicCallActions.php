<?php

namespace UWMadison\CallLog;

use Redcap;

trait BasicCallActions
{
    public function reportDisconnectedPhone($project_id, $record)
    {
        $config = $this->loadBadPhoneConfig();
        if ($config['_missing']) return;
        $event = $config['event'];
        $oldNotes = REDCap::getData($project_id, 'array', $record, $config['notes'], $event)[$record][$event][$config['notes']];
        $callData = end($this->getAllCallData($project_id, $record));
        if ($callData['call_disconnected'][1] != "1") return;
        $write[$record][$event][$config['flag']] = "1";
        $write[$record][$event][$config['notes']] = $callData['call_open_date'] . ' ' . $callData['call_open_time'] . ' ' . $callData['call_open_user_full_name'] . ': ' . $callData['call_notes'] . "\r\n\r\n" . $oldNotes;
        REDCap::saveData($project_id, 'array', $write, 'overwrite');
    }

    public function updateDisconnectedPhone($project_id, $record)
    {
        $config = $this->loadBadPhoneConfig();
        if ($config['_missing']) return;
        $event = $config['event'];
        $isResolved = REDCap::getData(
            $project_id,
            'array',
            $record,
            $config['resolved'],
            $event
        )[$record][$event][$config['resolved']][1] == "1";
        if (!$isResolved) return;
        $write[$record][$event][$config['flag']] = "";
        $write[$record][$event][$config['notes']] = "";
        $write[$record][$event][$config['resolved']][1] = "0";
        REDCap::saveData($project_id, 'array', $write, 'overwrite');
    }

    public function noCallsToday($project_id, $record, $metadata, $call_id)
    {
        if (!empty($metadata[$call_id])) {
            if (!is_array($metadata[$call_id]['noCallsToday'])) {
                $metadata[$call_id]['noCallsToday'] = [];
            }
            $metadata[$call_id]['noCallsToday'][] = date('Y-m-d');
        }
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }

    public function callStarted($project_id, $record, $metadata, $call_id = null, $user = null)
    {
        if (empty($metadata)) return;
        if ($call_id && $user) {
            $metadata[$call_id]['callStarted'] = date("Y-m-d H:i");
            $metadata[$call_id]['callStartedBy'] = $user;
            return $this->saveCallMetadata($project_id, $record, $metadata);
        }
        // Update progress for ongoing call
        $grace = strtotime('-' . $this->startedCallGrace . ' minutes'); // grace minutes ago
        $now = date("Y-m-d H:i");
        $user = $this->getUser()->getUsername();
        foreach ($metadata as $id => $call) {
            if (!$call['complete'] && ($call['callStartedBy'] == $user) && (strtotime($call['callStarted']) > $grace))
                $metadata[$id]['callStarted'] = $now;
        }
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }

    public function callEnded($project_id, $record, $metadata, $call_id)
    {
        if (!empty($metadata[$call_id]))
            $metadata[$call_id]['callStarted'] = '';
        return $this->saveCallMetadata($project_id, $record, $metadata);
    }
}
