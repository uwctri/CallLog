<?php

namespace UWMadison\CallLog;

use REDCap;

trait Utility
{
    private $dateMathCutoff = 5; # Date+/- less than N days will avoid weekends/holidays
    private $holidays = ['12-25', '12-24', '12-31', '07-04', '01-01']; # Fixed holidays

    private function includeJs($path, $defer = false, $module = false)
    {
        $defer = $defer ? "defer " : "";
        $module = $module ? "type=\"module\" " : "";
        echo '<script ' . $module . $defer . 'src="' . $this->getUrl($path) . '"></script>';
    }

    private function includeCss($path)
    {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '"/>';
    }

    private function passArgument($name, $value)
    {
        echo "<script>" . $this->module_global . "." . $name . " = " . json_encode($value) . ";</script>";
    }

    private function isNotBlank($value)
    {
        if (!is_array($value)) return $value != "";
        foreach ($value as $k => $v)
            if ($v != "") return True;
        return False;
    }

    // Fetch the un-piped record label used on a project
    private function getRecordLabel($project_id = null)
    {
        $project_id = $project_id ?? $_GET['pid'];
        $sql = "SELECT custom_record_label FROM redcap_projects WHERE project_id = ?";
        $query = $this->query($sql, [$project_id]);
        return array_values($query->fetch_assoc())[0];
    }

    // Get mapped values outside of a DD, returns code:text mapping
    private function explodeCodedValueText($text)
    {
        $text = array_map(function ($line) {
            return array_map('trim', explode(',', $line));
        }, explode("\n", $text));
        return array_combine(array_column($text, 0), array_column($text, 1));
    }

    // Returns a map from unique:display name
    private function getEventNameMap()
    {
        $eventNames = array_values(REDCap::getEventNames());
        foreach (array_values(REDCap::getEventNames(true)) as $i => $unique)
            $eventMap[$unique] = $eventNames[$i];
        return $eventMap;
    }

    // Get mapping for a coded value in the DD, returns code:text mapping
    private function getDictionaryValuesFor($key, $dataDictionary = null)
    {
        $dataDictionary = $dataDictionary ?? REDCap::getDataDictionary('array');
        $dataDictionary = $dataDictionary[$key]['select_choices_or_calculations'];
        $split = explode('|', $dataDictionary);
        $mapped = array_map(function ($value) {
            $arr = explode(', ', trim($value));
            $sliced = array_slice($arr, 1, count($arr) - 1, true);
            return array($arr[0] => implode(', ', $sliced));
        }, $split);
        array_walk_recursive($mapped, function ($v, $k) use (&$return) {
            $return[$k] = $v;
        });
        return $return;
    }

    // Get a map of username:full_name for users on the project
    private function getUserNameMap($project_id = null)
    {
        $map = [];
        $project_id = $project_id ?? $_GET['pid'];
        $sql = "
        SELECT u.username, u.full_name FROM redcap_user_rights ur
        LEFT JOIN (SELECT
            lower(trim(i.username)) AS username,
            trim(concat(i.user_firstname, ' ', i.user_lastname)) AS full_name
        FROM redcap_user_information i
        WHERE i.username != '') u ON ur.username = u.username
        where project_id = ?";
        $result = $this->query($sql, [$project_id]);
        while ($row = $result->fetch_assoc()) {
            $map[$row['username']] = $row['full_name'];
        }
        return $map;
    }

    // Get the first (or only) event that an instrument is on
    private function getEventOfInstrument($instrument)
    {
        global $Proj;
        $events = [];
        $validEvents = array_keys($Proj->eventInfo);
        $sql = "SELECT event_id FROM redcap_events_forms WHERE form_name = ?";
        $result = $this->query($sql, [$instrument]);
        while ($row = $result->fetch_assoc()) {
            $events[] = $row['event_id'];
        }
        return reset(array_intersect($events, $validEvents));
    }

    // Get record_id or real name for a project
    private function getRecordIdField($project_id)
    {
        $sql = "SELECT field_name FROM redcap_metadata WHERE field_order = 1 AND project_id = ?";
        $result = $this->query($sql, [$project_id]);
        return $result->fetch_assoc()["field_name"];
    }

    private function dateMath($date, $operation, $days)
    {
        $date = date('Y-m-d', strtotime("$date {$operation}{$days} days"));
        if ($days > $this->dateMathCutoff) return $date;
        // If sooner than 5 days then make sure its not starting on Weekend/Holiday
        $day = date("l", strtotime($date));
        $logic = [
            "Saturday+" => "+2",
            "Saturday-" => "-1",
            "Sunday+" => "+1",
            "Sunday-" => "-2",
        ][$day . $operation] ?? "+0";
        $date = date('Y-m-d', strtotime("$date $logic days"));
        $holidayOffset = in_array(date('m-d', strtotime($date)), $this->holidays) ? "1" : "0";
        return date('Y-m-d', strtotime("$date {$operation}{$holidayOffset} days"));
    }

    private function projectLog($action, $record = null, $event_id = null, $project_id = null)
    {
        $record = $record ?? $_GET['id'];
        $event_id = $event_id ?? $_GET['event_id'];
        $project_id = $project_id ?? $_GET['pid'];
        $sql = null;
        REDCap::logEvent("Call Log", $action, $sql, $record, $event_id, $project_id);
    }
}
