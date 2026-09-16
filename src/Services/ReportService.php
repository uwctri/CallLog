<?php

namespace UWMadison\CallLog\Services;

use REDCap;
use Throwable;
use ExternalModules\ExternalModules;
use UWMadison\CallLog\CallMetadataRepository;

class ReportService
{
    private $module;
    private ConfigService $configService;
    private CallMetadataRepository $metadataRepo;

    public function __construct($module, ConfigService $configService, CallMetadataRepository $metadataRepo)
    {
        $this->module = $module;
        $this->configService = $configService;
        $this->metadataRepo = $metadataRepo;
    }

    /**
     * Compile comprehensive report and analytics data for a given project and timeframe
     *
     * @param int $projectId
     * @param string $timeframe '7d', '30d', '90d', or 'all'
     * @return array
     */
    public function getReportsData(int $projectId, string $timeframe = '30d'): array
    {
        $startDate = null;
        if ($timeframe === '7d') {
            $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
        } elseif ($timeframe === '30d') {
            $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
        } elseif ($timeframe === '90d') {
            $startDate = date('Y-m-d 00:00:00', strtotime('-90 days'));
        }

        $userNameMap = $this->module->getUserNameMap($projectId);
        $callerStats = $this->getCallerProductivity($projectId, $startDate, $userNameMap);
        $queueStats = $this->getQueueAndPipelineStats($projectId, $startDate);
        $cronDiagnostics = $this->getCronDiagnostics($projectId);

        return [
            'timeframe' => $timeframe,
            'startDate' => $startDate,
            'generatedAt' => date('Y-m-d H:i:s'),
            'callerProductivity' => $callerStats,
            'queueSummary' => $queueStats,
            'cronDiagnostics' => $cronDiagnostics
        ];
    }

    /**
     * Compute productivity per user based on logged attempts and completed calls
     */
    private function getCallerProductivity(int $projectId, ?string $startDate, array $userNameMap): array
    {
        $table = REDCap::getDataTable($projectId);
        $callEvent = $this->metadataRepo->getEventOfInstrument($projectId, "call_log");
        $metaEvent = $this->metadataRepo->getEventOfInstrument($projectId, "call_log_metadata");

        $users = [];

        $ensureUser = function (string $key, ?string $displayName = null) use (&$users, $userNameMap) {
            $cleanedKey = trim($key);
            if ($cleanedKey === '') return null;
            if (!isset($users[$cleanedKey])) {
                $name = $displayName ?: ($userNameMap[$cleanedKey] ?? $cleanedKey);
                $users[$cleanedKey] = [
                    'username' => $cleanedKey,
                    'displayName' => $name,
                    'attempts' => 0,
                    'completed' => 0,
                    'voicemails' => 0,
                    'callbacksScheduled' => 0,
                    'totalDurationMinutes' => 0,
                    'callsStarted' => 0,
                    'isAutomation' => ($cleanedKey === 'REDCap' || strtolower($cleanedKey) === 'automation')
                ];
            }
            return $cleanedKey;
        };

        if ($callEvent) {
            // Aggregate attempts, voicemails, callbacks, and durations from call_log repeat instances
            $fieldsNeeded = [
                'call_open_user',
                'call_open_user_full_name',
                'call_open_datetime',
                'call_left_message',
                'call_requested_callback',
                'call_notes'
            ];
            $inClause = implode(',', array_fill(0, count($fieldsNeeded), '?'));

            $dateClause = "";
            $params = array_merge([$projectId, $callEvent], $fieldsNeeded);
            if ($startDate) {
                // If filtering by date, we restrict to instances where call_open_datetime >= startDate
                $dateClause = " AND (record, COALESCE(instance, 1)) IN (
                    SELECT record, COALESCE(instance, 1) 
                    FROM {$table} 
                    WHERE project_id = ? AND event_id = ? AND field_name = 'call_open_datetime' AND value >= ?
                )";
                $params[] = $projectId;
                $params[] = $callEvent;
                $params[] = $startDate;
            }

            $sql = "SELECT record, COALESCE(instance, 1) as inst, field_name, value 
                    FROM {$table} 
                    WHERE project_id = ? AND event_id = ? AND field_name IN ({$inClause}) {$dateClause}";

            try {
                $result = ExternalModules::query($sql, $params);
                $instanceRows = [];
                while ($row = $result->fetch_assoc()) {
                    $rKey = $row['record'] . '::' . $row['inst'];
                    $instanceRows[$rKey][$row['field_name']] = $row['value'];
                }

                foreach ($instanceRows as $data) {
                    $u = trim((string)($data['call_open_user'] ?? ''));
                    $fullName = trim((string)($data['call_open_user_full_name'] ?? ''));
                    if ($u === '' && $fullName !== '') $u = $fullName;
                    if ($u === '') continue;

                    $uKey = $ensureUser($u, $fullName ?: null);
                    if (!$uKey) continue;

                    $users[$uKey]['attempts']++;

                    if (!empty($data['call_left_message']) && $data['call_left_message'] == '1') $users[$uKey]['voicemails']++;
                    if (!empty($data['call_requested_callback']) && $data['call_requested_callback'] == '1') $users[$uKey]['callbacksScheduled']++;
                }
            } catch (Throwable $e) {
                error_log("[CallLog ReportService] Query error in attempts aggregation: " . $e->getMessage());
            }
        }

        // Count completed calls and auto-completions from call_metadata
        if ($metaEvent) {
            $sqlMeta = "SELECT value FROM {$table} WHERE project_id = ? AND field_name = 'call_metadata' AND event_id = ?";
            try {
                $metaRes = ExternalModules::query($sqlMeta, [$projectId, $metaEvent]);
                while ($row = $metaRes->fetch_assoc()) {
                    $raw = $row['value'] ?? '';
                    if (empty($raw)) continue;
                    $calls = json_decode($raw, true);
                    if (!is_array($calls)) continue;

                    foreach ($calls as $call) {
                        $status = $call['status'] ?? '';
                        $completedBy = trim((string)($call['completedBy'] ?? ''));
                        $callStartedBy = trim((string)($call['callStartedBy'] ?? ''));
                        $completedTime = $call['completedTime'] ?? '';

                        // Date filter on completion time if applicable
                        if ($startDate && !empty($completedTime) && $completedTime < $startDate) continue;

                        if ($status === 'complete') {
                            $targetCompleter = $completedBy !== '' ? $completedBy : ($callStartedBy !== '' ? $callStartedBy : 'REDCap');
                            $uKey = $ensureUser($targetCompleter);
                            if ($uKey) $users[$uKey]['completed']++;
                        }

                        if ($callStartedBy !== '') {
                            $uKey = $ensureUser($callStartedBy);
                            if ($uKey) {
                                $users[$uKey]['callsStarted']++;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log("[CallLog ReportService] Query error in metadata completion aggregation: " . $e->getMessage());
            }
        }

        // Calculate rates and averages
        $callerList = [];
        foreach ($users as $user) {
            $completionRate = ($user['attempts'] > 0)
                ? round(($user['completed'] / $user['attempts']) * 100, 1)
                : ($user['completed'] > 0 ? 100.0 : 0.0);

            $user['completionRate'] = $completionRate;
            $callerList[] = $user;
        }

        // Sort callerList by attempts descending, with REDCap automation at the end
        usort($callerList, function ($a, $b) {
            if ($a['isAutomation'] !== $b['isAutomation']) {
                return $a['isAutomation'] ? 1 : -1;
            }
            return $b['attempts'] <=> $a['attempts'];
        });

        return $callerList;
    }

    /**
     * Compute total queue size, status breakdown (active, completed, expired), and breakdown by call template
     */
    private function getQueueAndPipelineStats(int $projectId, ?string $startDate): array
    {
        $table = REDCap::getDataTable($projectId);
        $metaEvent = $this->metadataRepo->getEventOfInstrument($projectId, "call_log_metadata");

        $stats = [
            'totalCalls' => 0,
            'activeCalls' => 0,
            'completedCalls' => 0,
            'expiredCalls' => 0,
            'expiredReminders' => 0,
            'byTemplate' => [
                'reminder' => ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0],
                'followup' => ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0],
                'mcv' => ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0],
                'nts' => ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0],
                'new' => ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0],
                'visit' => ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0],
                'adhoc' => ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0]
            ],
            'totalAttemptsAcrossRecords' => 0
        ];

        if ($metaEvent) {
            $sql = "SELECT value FROM {$table} WHERE project_id = ? AND field_name = 'call_metadata' AND event_id = ?";
            try {
                $result = ExternalModules::query($sql, [$projectId, $metaEvent]);
                while ($row = $result->fetch_assoc()) {
                    $raw = $row['value'] ?? '';
                    if (empty($raw)) continue;
                    $calls = json_decode($raw, true);
                    if (!is_array($calls)) continue;

                    foreach ($calls as $call) {
                        $template = $call['template'] ?? 'adhoc';
                        if (!isset($stats['byTemplate'][$template])) {
                            $stats['byTemplate'][$template] = ['total' => 0, 'active' => 0, 'complete' => 0, 'expired' => 0];
                        }

                        $status = $call['status'] ?? 'incomplete';
                        $stats['totalCalls']++;
                        $stats['byTemplate'][$template]['total']++;

                        if ($status === 'complete') {
                            $stats['completedCalls']++;
                            $stats['byTemplate'][$template]['complete']++;
                        } elseif ($status === 'expired') {
                            $stats['expiredCalls']++;
                            $stats['byTemplate'][$template]['expired']++;
                            if ($template === 'reminder') {
                                $stats['expiredReminders']++;
                            }
                        } else {
                            $stats['activeCalls']++;
                            $stats['byTemplate'][$template]['active']++;
                        }

                        if (!empty($call['instances']) && is_array($call['instances'])) {
                            $stats['totalAttemptsAcrossRecords'] += count($call['instances']);
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log("[CallLog ReportService] Query error in queue stats: " . $e->getMessage());
            }
        }

        $stats['completionPercentage'] = ($stats['totalCalls'] > 0)
            ? round(($stats['completedCalls'] / $stats['totalCalls']) * 100, 1)
            : 0.0;

        $stats['avgAttemptsPerCall'] = ($stats['totalCalls'] > 0)
            ? round($stats['totalAttemptsAcrossRecords'] / $stats['totalCalls'], 2)
            : 0.0;

        return $stats;
    }

    /**
     * Inspect REDCap external module log for recent cron executions and automated lifecycle changes
     */
    private function getCronDiagnostics(int $projectId): array
    {
        $diagnostics = [
            'temporalCron' => [
                'lastRun' => null,
                'lastRunFormatted' => 'Never',
                'duration' => null,
                'recordsEvaluated' => 0,
                'callsUpdated' => 0,
                'status' => 'idle' // 'healthy', 'warning', 'idle'
            ],
            'dailySync' => [
                'lastRun' => null,
                'lastRunFormatted' => 'Never',
                'duration' => null,
                'recordsEvaluated' => 0,
                'callsGenerated' => 0,
                'status' => 'idle'
            ],
            'recentSystemLogs' => []
        ];

        try {
            // 1. Query latest batch generation runs (cron_temporal and cron_daily)
            $resBatch = $this->module->queryLogs(
                "select log_id, timestamp, message, `trigger`, records_evaluated, calls_generated, duration_seconds
                 where project_id = ? and message like 'Call generation batch completed%'
                 order by log_id desc limit 10",
                [$projectId]
            );
            $foundTemporal = false;
            $foundDaily = false;
            $now = time();

            while ($row = $resBatch->fetch_assoc()) {
                $trig = $row['trigger'] ?? '';
                $ts = !empty($row['timestamp']) ? strtotime($row['timestamp']) : null;
                $formattedTime = $ts ? date('M j, Y g:i A', $ts) : 'Unknown';
                $diffHours = $ts ? ($now - $ts) / 3600 : 999;

                if (!$foundTemporal && ($trig === 'cron_temporal' || $trig === 'cron_hourly')) {
                    $diagnostics['temporalCron'] = [
                        'lastRun' => $row['timestamp'],
                        'lastRunFormatted' => $formattedTime,
                        'duration' => round((float)($row['duration_seconds'] ?? 0), 2),
                        'recordsEvaluated' => (int)($row['records_evaluated'] ?? 0),
                        'callsUpdated' => (int)($row['calls_generated'] ?? 0),
                        'status' => ($diffHours <= 2.5) ? 'healthy' : ($diffHours <= 6 ? 'warning' : 'idle')
                    ];
                    $foundTemporal = true;
                }

                if (!$foundDaily && ($trig === 'cron_daily' || $trig === 'manual_dashboard')) {
                    $diagnostics['dailySync'] = [
                        'lastRun' => $row['timestamp'],
                        'lastRunFormatted' => $formattedTime,
                        'duration' => round((float)($row['duration_seconds'] ?? 0), 2),
                        'recordsEvaluated' => (int)($row['records_evaluated'] ?? 0),
                        'callsGenerated' => (int)($row['calls_generated'] ?? 0),
                        'status' => ($diffHours <= 30) ? 'healthy' : 'warning'
                    ];
                    $foundDaily = true;
                }
            }

            // 2. Query recent automated lifecycle events (auto-completed, expired, generated)
            $resRecent = $this->module->queryLogs(
                "select log_id, timestamp, message, record, action, call_name, reason, template
                 where project_id = ?
                 order by log_id desc limit 6",
                [$projectId]
            );
            while ($row = $resRecent->fetch_assoc()) {
                $ts = !empty($row['timestamp']) ? strtotime($row['timestamp']) : time();
                $diagnostics['recentSystemLogs'][] = [
                    'logId' => $row['log_id'],
                    'timestamp' => date('M j, g:i A', $ts),
                    'record' => $row['record'] ?? '',
                    'action' => $row['action'] ?? '',
                    'callName' => $row['call_name'] ?? '',
                    'reason' => $row['reason'] ?? '',
                    'template' => $row['template'] ?? '',
                    'message' => $row['message'] ?? ''
                ];
            }
        } catch (Throwable $e) {
            error_log("[CallLog ReportService] Query error in cron diagnostics: " . $e->getMessage());
        }

        return $diagnostics;
    }
}
