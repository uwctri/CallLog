<?php

namespace UWMadison\CallLog;

use REDCap;
use Project;
use ExternalModules\ExternalModules;

class CallMetadataRepository
{
    private string $metadataField = "call_metadata";
    private string $instrumentMeta = "call_log_metadata";
    private string $instrumentCall = "call_log";

    public function getMetadata(int $projectId, string $record): array
    {
        $metaEvent = $this->getEventOfInstrument($projectId, $this->instrumentMeta);
        if (!$metaEvent) {
            return [];
        }

        $data = REDCap::getData($projectId, 'array', $record, $this->metadataField);
        $raw = $data[$record][$metaEvent][$this->metadataField] ?? '';

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, CallItemDTO>
     */
    public function getMetadataDTOs(int $projectId, string $record): array
    {
        $raw = $this->getMetadata($projectId, $record);
        $dtos = [];
        foreach ($raw as $key => $item) {
            if (is_array($item)) {
                $item['id'] = $item['id'] ?? (string)$key;
                $dtos[$key] = new CallItemDTO($item);
            }
        }
        return $dtos;
    }

    public function saveMetadata(int $projectId, string $record, array $data): bool
    {
        $metaEvent = $this->getEventOfInstrument($projectId, $this->instrumentMeta);
        if (!$metaEvent) {
            return false;
        }

        $table = REDCap::getDataTable($projectId);
        $sql = "SELECT field_name FROM {$table} WHERE project_id = ? AND record = ? LIMIT 1";
        $result = ExternalModules::query($sql, [$projectId, $record]);
        if (empty($result->fetch_assoc())) {
            return false;
        }

        $saveData = [
            $record => [
                $metaEvent => [
                    $this->metadataField => json_encode($data)
                ]
            ]
        ];

        $response = REDCap::saveData($projectId, 'array', $saveData);
        return empty($response['errors']);
    }

    /**
     * @param array<string, CallItemDTO> $dtos
     */
    public function saveMetadataDTOs(int $projectId, string $record, array $dtos): bool
    {
        $data = [];
        foreach ($dtos as $key => $dto) {
            $data[$key] = $dto instanceof CallItemDTO ? $dto->toArray() : $dto;
        }
        return $this->saveMetadata($projectId, $record, $data);
    }

    public function deleteLastCallInstance(int $projectId, string $record): bool
    {
        $callEvent = $this->getEventOfInstrument($projectId, $this->instrumentCall);
        if (!$callEvent) {
            return false;
        }

        $data = REDCap::getData($projectId, 'array', $record, null, $callEvent);
        $repeatInstances = $data[$record]['repeat_instances'][$callEvent][$this->instrumentCall] ?? [];
        $lastInstance = array_key_last($repeatInstances);
        $instance = $lastInstance ? (int)$lastInstance : 1;

        $metadata = $this->getMetadata($projectId, $record);
        foreach ($metadata as $index => $call) {
            $tmp = $call['instances'] ?? [];
            $metadata[$index]['instances'] = array_values(array_diff($tmp, [(string)$instance, $instance]));
            if (!empty($tmp) && count($tmp) !== count($metadata[$index]['instances'])) {
                $metadata[$index]['complete'] = false;
            }
        }

        $fields = array_values(array_intersect(
            REDCap::getFieldNames($this->instrumentCall),
            array_keys($data[$record][$callEvent] ?? [])
        ));

        if (!empty($fields)) {
            $table = REDCap::getDataTable($projectId);
            $inClause = implode(',', array_fill(0, count($fields), '?'));

            if ($instance > 1) {
                $sql = "DELETE FROM {$table} WHERE project_id = ? AND record = ? AND instance = ? AND field_name IN ({$inClause})";
                $params = array_merge([$projectId, $record, $instance], $fields);
            } else {
                $sql = "DELETE FROM {$table} WHERE project_id = ? AND record = ? AND instance IS NULL AND field_name IN ({$inClause})";
                $params = array_merge([$projectId, $record], $fields);
            }

            ExternalModules::query($sql, $params);
        }

        return $this->saveMetadata($projectId, $record, $metadata);
    }

    public function getEventOfInstrument(int $projectId, string $instrument): ?int
    {
        global $Proj;
        if (!isset($Proj) || $Proj->project_id != $projectId) {
            $Proj = new Project($projectId);
        }

        $events = [];
        $validEvents = array_keys($Proj->eventInfo ?? []);
        $sql = "SELECT event_id FROM redcap_events_forms WHERE form_name = ?";
        $result = ExternalModules::query($sql, [$instrument]);
        while ($row = $result->fetch_assoc()) {
            $events[] = (int)$row['event_id'];
        }

        $intersect = array_intersect($events, $validEvents);
        return !empty($intersect) ? reset($intersect) : null;
    }

    public function getTotalCallsCount(int $projectId): int
    {
        $metaEvent = $this->getEventOfInstrument($projectId, $this->instrumentMeta);
        if (!$metaEvent) {
            return 0;
        }

        $table = REDCap::getDataTable($projectId);
        $sql = "SELECT value FROM {$table} WHERE project_id = ? AND field_name = ? AND event_id = ?";
        $result = ExternalModules::query($sql, [$projectId, $this->metadataField, $metaEvent]);

        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $val = $row['value'] ?? '';
            if (empty($val)) continue;
            $decoded = json_decode($val, true);
            if (is_array($decoded)) {
                $total += count($decoded);
            }
        }
        return $total;
    }
}
