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
    private array $eventOfInstrumentCache = [];
    private array $existingRecordCache = [];

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

        $this->existingRecordCache["{$projectId}_{$record}"] = true;

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

        if (empty($this->existingRecordCache["{$projectId}_{$record}"])) {
            $table = REDCap::getDataTable($projectId);
            $sql = "SELECT field_name FROM {$table} WHERE project_id = ? AND record = ? LIMIT 1";
            $result = ExternalModules::query($sql, [$projectId, $record]);
            if (empty($result->fetch_assoc())) {
                return false;
            }
            $this->existingRecordCache["{$projectId}_{$record}"] = true;
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
        $cacheKey = "{$projectId}_{$instrument}";
        if (array_key_exists($cacheKey, $this->eventOfInstrumentCache)) {
            return $this->eventOfInstrumentCache[$cacheKey];
        }

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
        return $this->eventOfInstrumentCache[$cacheKey] = (!empty($intersect) ? reset($intersect) : null);
    }

    public function getCallLogStats(int $projectId): array
    {
        $stats = [
            'totalCalls' => 0,
            'completedCalls' => 0,
            'totalAttempts' => 0,
            'uniqueCallers' => 0,
        ];

        try {
            $table = REDCap::getDataTable($projectId);
            $metaEvent = $this->getEventOfInstrument($projectId, $this->instrumentMeta);
            $callEvent = $this->getEventOfInstrument($projectId, $this->instrumentCall);

            $callers = [];

            if ($metaEvent) {
                $sql = "SELECT value FROM {$table} WHERE project_id = ? AND field_name = ? AND event_id = ?";
                $result = ExternalModules::query($sql, [$projectId, $this->metadataField, $metaEvent]);

                while ($row = $result->fetch_assoc()) {
                    $val = $row['value'] ?? '';
                    if (empty($val)) continue;
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $call) {
                            $stats['totalCalls']++;
                            if (!empty($call['complete'])) {
                                $stats['completedCalls']++;
                            }
                            if (!empty($call['completedBy'])) {
                                $callers[] = trim((string)$call['completedBy']);
                            }
                            if (!empty($call['callStartedBy'])) {
                                $callers[] = trim((string)$call['callStartedBy']);
                            }
                        }
                    }
                }
            }

            if ($callEvent) {
                // Count distinct logged call attempts (each record + instance)
                $sqlAttempts = "SELECT COUNT(DISTINCT record, COALESCE(instance, 1)) as cnt FROM {$table} WHERE project_id = ? AND event_id = ? AND field_name IN ('call_open_date', 'call_attempt')";
                $resAttempts = ExternalModules::query($sqlAttempts, [$projectId, $callEvent]);
                if ($resAttempts && ($row = $resAttempts->fetch_assoc())) {
                    $stats['totalAttempts'] = (int)($row['cnt'] ?? 0);
                }

                // Query distinct callers who have opened or logged calls
                $sqlCallers = "SELECT DISTINCT value FROM {$table} WHERE project_id = ? AND event_id = ? AND field_name = 'call_open_user' AND value IS NOT NULL AND value != ''";
                $resCallers = ExternalModules::query($sqlCallers, [$projectId, $callEvent]);
                $foundUsername = false;
                if ($resCallers) {
                    while ($row = $resCallers->fetch_assoc()) {
                        $val = trim((string)($row['value'] ?? ''));
                        if (!empty($val)) {
                            $callers[] = $val;
                            $foundUsername = true;
                        }
                    }
                }

                // If call_open_user had no entries, fallback to call_open_user_full_name
                if (!$foundUsername) {
                    $sqlFull = "SELECT DISTINCT value FROM {$table} WHERE project_id = ? AND event_id = ? AND field_name = 'call_open_user_full_name' AND value IS NOT NULL AND value != ''";
                    $resFull = ExternalModules::query($sqlFull, [$projectId, $callEvent]);
                    if ($resFull) {
                        while ($row = $resFull->fetch_assoc()) {
                            $val = trim((string)($row['value'] ?? ''));
                            if (!empty($val)) {
                                $callers[] = $val;
                            }
                        }
                    }
                }
            }

            $stats['uniqueCallers'] = count(array_unique(array_filter($callers)));
        } catch (\Throwable $e) {
            // Gracefully catch query failures
        }

        return $stats;
    }

    public function getTotalCallsCount(int $projectId): int
    {
        $stats = $this->getCallLogStats($projectId);
        return $stats['totalCalls'] ?? 0;
    }
}
