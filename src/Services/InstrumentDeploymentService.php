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
     * Also marks call_log as repeatable in redcap_events_repeat.
     *
     * @param int $projectId
     * @param int $eventId
     * @return void
     */
    public function assignToSingleEvent(int $projectId, int $eventId): void
    {
        // First remove any stale assignments to ensure strict single-event binding
        $sqlDelete = "DELETE FROM redcap_events_forms WHERE form_name IN ('{$this->instrumentCall}', '{$this->instrumentMeta}') AND event_id IN (SELECT event_id FROM redcap_events_metadata WHERE arm_id IN (SELECT arm_id FROM redcap_events_arms WHERE project_id = ?))";
        ExternalModules::query($sqlDelete, [$projectId]);

        // Insert fresh single-event designation
        $sqlInsert = "INSERT IGNORE INTO redcap_events_forms (event_id, form_name) VALUES (?, '{$this->instrumentCall}'), (?, '{$this->instrumentMeta}')";
        ExternalModules::query($sqlInsert, [$eventId, $eventId]);

        // Also ensure call_log is marked as repeatable on this event
        $sqlRepeat = "INSERT IGNORE INTO redcap_events_repeat (event_id, form_name) VALUES (?, '{$this->instrumentCall}')";
        ExternalModules::query($sqlRepeat, [$eventId]);
    }

    /**
     * Checks event assignment and repeatability for call_log and call_log_metadata instruments.
     *
     * @param int $projectId
     * @return array
     */
    public function checkInstrumentConfiguration(int $projectId): array
    {
        global $Proj;
        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new Project($projectId);
        }

        $isDeployed = isset($Proj->forms[$this->instrumentCall]) && isset($Proj->forms[$this->instrumentMeta]);
        if (!$isDeployed) {
            return [
                'deployed' => false,
                'singleEventValid' => false,
                'repeatableValid' => false,
                'valid' => false,
                'assignedEventId' => null,
                'assignedEventName' => null,
                'isLongitudinal' => !empty($Proj->longitudinal),
                'eventMessage' => 'Call Log instruments have not been deployed yet.',
                'repeatableMessage' => 'Deploy instruments first.'
            ];
        }

        $events = $Proj->eventInfo ?? [];
        $isLongitudinal = !empty($Proj->longitudinal);
        $validEventIds = array_keys($events);

        $singleEventValid = false;
        $assignedEventId = null;
        $assignedEventName = null;
        $eventMessage = '';

        if (!$isLongitudinal || count($events) <= 1) {
            // Classic project: automatically single event
            $singleEventValid = true;
            $assignedEventId = !empty($events) ? (int)array_key_first($events) : null;
            $assignedEventName = $assignedEventId && isset($events[$assignedEventId]['name_ext']) ? $events[$assignedEventId]['name_ext'] : 'Default Event';
        } else {
            // Longitudinal project: query redcap_events_forms
            $inClause = implode(',', array_fill(0, count($validEventIds), '?'));
            $sql = "SELECT DISTINCT event_id, form_name 
                    FROM redcap_events_forms 
                    WHERE form_name IN ('{$this->instrumentCall}', '{$this->instrumentMeta}') 
                    AND event_id IN ({$inClause})";

            $result = ExternalModules::query($sql, $validEventIds);
            $assignedEvents = [];
            while ($row = $result->fetch_assoc()) {
                $eId = (int)$row['event_id'];
                $assignedEvents[$eId][] = $row['form_name'];
            }

            $numEvents = count($assignedEvents);
            if ($numEvents === 0) {
                $singleEventValid = false;
                $eventMessage = 'Instruments are not designated to any event. Please assign them to exactly 1 event.';
            } elseif ($numEvents > 1) {
                $singleEventValid = false;
                $eventNames = [];
                foreach (array_keys($assignedEvents) as $eId) {
                    $eventNames[] = $events[$eId]['name_ext'] ?? "Event ID $eId";
                }
                $eventMessage = 'Instruments are assigned across multiple events (' . implode(', ', $eventNames) . '). Both must be on only 1 event.';
            } else {
                $eId = array_key_first($assignedEvents);
                $formsOnEvent = $assignedEvents[$eId];
                $hasCall = in_array($this->instrumentCall, $formsOnEvent, true);
                $hasMeta = in_array($this->instrumentMeta, $formsOnEvent, true);

                if ($hasCall && $hasMeta) {
                    $singleEventValid = true;
                    $assignedEventId = $eId;
                    $assignedEventName = $events[$eId]['name_ext'] ?? "Event ID $eId";
                } else {
                    $missing = !$hasCall ? 'call_log' : 'call_log_metadata';
                    $singleEventValid = false;
                    $eventMessage = "Both instruments must be on the same event. Currently missing: {$missing}.";
                }
            }
        }

        // Check if call_log is repeatable on the target event
        $repeatableValid = false;
        $targetEventId = $assignedEventId ?? (!empty($events) ? (int)array_key_first($events) : null);

        if ($targetEventId) {
            if (isset($Proj) && method_exists($Proj, 'isRepeatingFormOrEvent')) {
                $repeatableValid = (bool)$Proj->isRepeatingFormOrEvent($targetEventId, $this->instrumentCall);
            }
            if (!$repeatableValid) {
                $sqlRepeat = "SELECT 1 FROM redcap_events_repeat WHERE (form_name = '{$this->instrumentCall}' OR form_name IS NULL OR form_name = '') AND event_id = ? LIMIT 1";
                $resRepeat = ExternalModules::query($sqlRepeat, [$targetEventId]);
                if ($resRepeat && $resRepeat->fetch_assoc()) {
                    $repeatableValid = true;
                }
            }
        }

        if (!$repeatableValid && !empty($validEventIds)) {
            $inClause = implode(',', array_fill(0, count($validEventIds), '?'));
            $sqlRepeatAll = "SELECT event_id FROM redcap_events_repeat WHERE (form_name = '{$this->instrumentCall}' OR form_name IS NULL OR form_name = '') AND event_id IN ({$inClause}) LIMIT 1";
            $resRepeatAll = ExternalModules::query($sqlRepeatAll, $validEventIds);
            if ($resRepeatAll && ($row = $resRepeatAll->fetch_assoc())) {
                if ($singleEventValid && $assignedEventId && (int)$row['event_id'] !== $assignedEventId) {
                    $repeatableValid = false;
                } else {
                    $repeatableValid = true;
                    if (!$assignedEventId) {
                        $assignedEventId = (int)$row['event_id'];
                    }
                }
            }
        }

        $repeatableMessage = $repeatableValid 
            ? 'The call_log instrument is enabled as repeatable.'
            : 'The call_log instrument has not been enabled as repeatable.';

        $allValid = $isDeployed && $singleEventValid && $repeatableValid;

        return [
            'deployed' => $isDeployed,
            'singleEventValid' => $singleEventValid,
            'repeatableValid' => $repeatableValid,
            'valid' => $allValid,
            'assignedEventId' => $assignedEventId,
            'assignedEventName' => $assignedEventName,
            'isLongitudinal' => $isLongitudinal,
            'eventMessage' => $eventMessage,
            'repeatableMessage' => $repeatableMessage,
        ];
    }

    /**
     * Enables call_log as a repeatable instrument on the designated event.
     *
     * @param int $projectId
     * @param int|null $eventId
     * @return array
     */
    public function enableRepeatable(int $projectId, ?int $eventId = null): array
    {
        global $Proj;
        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new Project($projectId);
        }

        $events = $Proj->eventInfo ?? [];
        if (!$eventId) {
            $repo = new \UWMadison\CallLog\CallMetadataRepository();
            $eventId = $repo->getEventOfInstrument($projectId, $this->instrumentCall);
            if (!$eventId && !empty($events)) {
                $eventId = (int)array_key_first($events);
            }
        }

        if (!$eventId) {
            return [
                'success' => false,
                'message' => 'No valid project event found to enable repeatable call log.'
            ];
        }

        // If longitudinal and user specified eventId, also make sure both instruments are on this event
        if (!empty($Proj->longitudinal)) {
            $this->assignToSingleEvent($projectId, $eventId);
        } else {
            $sqlRepeat = "INSERT IGNORE INTO redcap_events_repeat (event_id, form_name) VALUES (?, '{$this->instrumentCall}')";
            ExternalModules::query($sqlRepeat, [$eventId]);
        }

        return [
            'success' => true,
            'message' => 'Call Log has been enabled as repeatable successfully.'
        ];
    }

    public function isDeployed(int $projectId): bool
    {
        global $Proj;
        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new Project($projectId);
        }
        return isset($Proj->forms[$this->instrumentCall]) && isset($Proj->forms[$this->instrumentMeta]);
    }
}
