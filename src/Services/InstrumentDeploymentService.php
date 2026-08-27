<?php

namespace UWMadison\CallLog\Services;

use REDCap;
use Project;
use Design;
use MetaData;
use ExternalModules\ExternalModules;

class InstrumentDeploymentService
{
    private string $instrumentCall = "call_log";
    private string $instrumentMeta = "call_log_metadata";

    /**
     * Deploys the call_log and call_log_metadata instruments from CSV template
     * and assigns them to a single specified event if the project has multiple events.
     *
     * @param int $projectId
     * @param string $csvPath
     * @param int|null $eventId
     * @return array Array containing 'success' (bool) and 'message' (string)
     */
    public function deploy(int $projectId, string $csvPath, ?int $eventId = null): array
    {
        global $Proj;
        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new Project($projectId);
        }

        $events = $Proj->eventInfo ?? [];
        $hasMultipleEvents = count($events) > 1;

        // Check if multiple events exist and ensure an eventId is selected
        if ($hasMultipleEvents) {
            if (!$eventId || !isset($events[$eventId])) {
                return [
                    'success' => false,
                    'message' => 'Multiple events detected on this project. Please select a single event to assign the Call Log instruments to.'
                ];
            }
        } else {
            // Single event project - automatically use the default event ID
            if (!$eventId && !empty($events)) {
                $eventId = (int)array_key_first($events);
            }
        }

        // Validate existing event assignments to ensure instruments are assigned to only ONE event
        $validation = $this->validateSingleEventAssignment($projectId);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }

        $existingInstruments = array_keys(REDCap::getInstrumentNames(null, $projectId));
        $callExists = in_array($this->instrumentCall, $existingInstruments, true);
        $metaExists = in_array($this->instrumentMeta, $existingInstruments, true);

        // If instruments do not exist yet, import them from the CSV data dictionary
        if (!$callExists || !$metaExists) {
            if (!file_exists($csvPath)) {
                return [
                    'success' => false,
                    'message' => 'Data dictionary file call.csv was not found.'
                ];
            }

            $dd = Design::excel_to_array($csvPath, ",");
            if (empty($dd)) {
                return [
                    'success' => false,
                    'message' => 'Data dictionary template is empty or invalid.'
                ];
            }

            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");

            MetaData::createDataDictionarySnapshot();

            $sql_errors = MetaData::save_metadata($dd, true, false, $projectId);
            $hasErrors = count($sql_errors) > 0;

            db_query($hasErrors ? "ROLLBACK" : "COMMIT");
            db_query("SET AUTOCOMMIT=1");

            if ($hasErrors) {
                return [
                    'success' => false,
                    'message' => 'Failed to deploy instruments into the data dictionary.'
                ];
            }
        }

        // Assign instruments to the selected single event in redcap_events_forms
        if ($eventId) {
            $this->assignToSingleEvent($projectId, $eventId);
        }

        return [
            'success' => true,
            'message' => 'Call Log instruments were deployed and assigned successfully.'
        ];
    }

    /**
     * Validates that call_log and call_log_metadata are assigned to AT MOST one single event.
     *
     * @param int $projectId
     * @return array
     */
    public function validateSingleEventAssignment(int $projectId): array
    {
        global $Proj;
        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new Project($projectId);
        }

        $validEventIds = array_keys($Proj->eventInfo ?? []);
        if (empty($validEventIds)) {
            return ['valid' => true];
        }

        $inClause = implode(',', array_fill(0, count($validEventIds), '?'));
        $sql = "SELECT DISTINCT event_id, form_name 
                FROM redcap_events_forms 
                WHERE form_name IN ('{$this->instrumentCall}', '{$this->instrumentMeta}') 
                AND event_id IN ({$inClause})";

        $result = ExternalModules::query($sql, $validEventIds);
        $assignedEvents = [];
        while ($row = $result->fetch_assoc()) {
            $assignedEvents[(int)$row['event_id']][] = $row['form_name'];
        }

        if (count($assignedEvents) > 1) {
            $eventNames = [];
            foreach (array_keys($assignedEvents) as $eId) {
                $eventNames[] = $Proj->eventInfo[$eId]['name_ext'] ?? "Event ID $eId";
            }
            $namesStr = implode(', ', $eventNames);

            return [
                'valid' => false,
                'message' => "Call Log instruments are currently assigned to multiple events ({$namesStr}). They must be assigned to exactly ONE single event."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Assigns call_log and call_log_metadata instruments to a single specified event_id in redcap_events_forms.
     *
     * @param int $projectId
     * @param int $eventId
     * @return void
     */
    private function assignToSingleEvent(int $projectId, int $eventId): void
    {
        // First remove any stale assignments to ensure strict single-event binding
        $sqlDelete = "DELETE FROM redcap_events_forms WHERE form_name IN ('{$this->instrumentCall}', '{$this->instrumentMeta}') AND event_id IN (SELECT event_id FROM redcap_events_metadata WHERE arm_id IN (SELECT arm_id FROM redcap_events_arms WHERE project_id = ?))";
        ExternalModules::query($sqlDelete, [$projectId]);

        // Insert fresh single-event designation
        $sqlInsert = "INSERT IGNORE INTO redcap_events_forms (event_id, form_name) VALUES (?, '{$this->instrumentCall}'), (?, '{$this->instrumentMeta}')";
        ExternalModules::query($sqlInsert, [$eventId, $eventId]);
    }
}
