<?php

namespace UWMadison\CallLog\Services;

class LoggingService
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * General log dispatch wrapping REDCap's native External Modules log()
     *
     * @param string $message
     * @param array $parameters
     * @return int|null Log ID if available
     */
    public function log(string $message, array $parameters = []): ?int
    {
        $sanitized = [];
        foreach ($parameters as $key => $value) {
            if ($value === null) {
                continue;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value ? '1' : '0';
            } elseif (is_scalar($value)) {
                $sanitized[$key] = (string)$value;
            } else {
                $sanitized[$key] = json_encode($value);
            }
        }

        try {
            if (is_object($this->module) && method_exists($this->module, 'log')) {
                return $this->module->log($message, $sanitized);
            }
        } catch (\Throwable $e) {
            // Silently fall back to error_log to never interrupt critical workflow
            error_log("[CallLog] LoggingService failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Log when a new call is generated for a participant record
     */
    public function logCallGenerated(
        int $projectId,
        string $record,
        string $callId,
        string $callName,
        string $template,
        string $trigger,
        array $details = []
    ): void {
        $message = "Call generated: {$callName} ({$callId}) for record {$record}";
        $params = array_merge([
            'action' => 'call_generated',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'call_name' => $callName,
            'template' => $template,
            'trigger' => $trigger
        ], $details);

        $this->log($message, $params);
    }

    /**
     * Log when call window / target dates shift
     */
    public function logCallUpdated(
        int $projectId,
        string $record,
        string $callId,
        string $callName,
        array $details = []
    ): void {
        $message = "Call window updated: {$callName} ({$callId}) for record {$record}";
        $params = array_merge([
            'action' => 'call_updated',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'call_name' => $callName
        ], $details);

        $this->log($message, $params);
    }

    /**
     * Log when REDCap automatically marks a call completed
     */
    public function logCallAutoCompleted(
        int $projectId,
        string $record,
        string $callId,
        string $callName,
        string $reason
    ): void {
        $message = "Call auto-completed by REDCap: {$callName} ({$callId}) for record {$record}";
        $this->log($message, [
            'action' => 'call_auto_completed',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'call_name' => $callName,
            'completed_by' => 'REDCap',
            'reason' => $reason
        ]);
    }

    /**
     * Log when an uncontacted call is automatically removed
     */
    public function logCallRemoved(
        int $projectId,
        string $record,
        string $callId,
        string $callName,
        string $reason
    ): void {
        $message = "Call removed: {$callName} ({$callId}) for record {$record}";
        $this->log($message, [
            'action' => 'call_auto_removed',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'call_name' => $callName,
            'reason' => $reason
        ]);
    }

    /**
     * Log summary of project-wide batch evaluation
     */
    public function logBatchGenerationSummary(
        int $projectId,
        string $trigger,
        int $recordsEvaluated,
        int $callsGenerated,
        float $durationSeconds
    ): void {
        $message = "Call generation batch completed: {$callsGenerated} call(s) generated across {$recordsEvaluated} record(s)";
        $this->log($message, [
            'action' => 'batch_generation_completed',
            'project_id' => $projectId,
            'trigger' => $trigger,
            'records_evaluated' => $recordsEvaluated,
            'calls_generated' => $callsGenerated,
            'duration_seconds' => round($durationSeconds, 3)
        ]);
    }

    /**
     * Log when an adhoc call is created
     */
    public function logAdhocCreated(
        int $projectId,
        string $record,
        string $callId,
        string $callName,
        string $reason,
        string $reporter,
        string $source = 'ui',
        array $details = []
    ): void {
        $message = "Adhoc call created: {$callName} for record {$record}";
        $params = array_merge([
            'action' => 'adhoc_created',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'call_name' => $callName,
            'reason' => $reason,
            'reporter' => $reporter,
            'source' => $source
        ], $details);

        $this->log($message, $params);
    }

    /**
     * Log when an adhoc call is resolved via API
     */
    public function logAdhocResolved(
        int $projectId,
        string $record,
        string $callTypeId,
        string $reason,
        int $resolvedCount
    ): void {
        $message = "Adhoc call resolved via API for record {$record}";
        $this->log($message, [
            'action' => 'adhoc_resolved',
            'project_id' => $projectId,
            'record' => $record,
            'call_type_id' => $callTypeId,
            'reason' => $reason,
            'resolved_count' => $resolvedCount
        ]);
    }

    /**
     * Log when coordinator starts call
     */
    public function logCallStarted(
        int $projectId,
        string $record,
        string $callId,
        string $user
    ): void {
        $message = "Call started by {$user} on call {$callId} for record {$record}";
        $this->log($message, [
            'action' => 'call_started',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'user' => $user
        ]);
    }

    /**
     * Log when coordinator ends call / releases active call lock
     */
    public function logCallEnded(
        int $projectId,
        string $record,
        string $callId,
        string $user
    ): void {
        $message = "Call lock released by {$user} on call {$callId} for record {$record}";
        $this->log($message, [
            'action' => 'call_ended',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'user' => $user
        ]);
    }

    /**
     * Log when coordinator snoozes call for today
     */
    public function logNoCallsToday(
        int $projectId,
        string $record,
        string $callId,
        string $user,
        string $date
    ): void {
        $message = "Call snoozed for today by {$user} on call {$callId} for record {$record}";
        $this->log($message, [
            'action' => 'no_calls_today',
            'project_id' => $projectId,
            'record' => $record,
            'call_id' => $callId,
            'user' => $user,
            'date' => $date
        ]);
    }

    /**
     * Log when a call log instance is deleted
     */
    public function logCallInstanceDeleted(
        int $projectId,
        string $record,
        string $user
    ): void {
        $message = "Call log instance deleted for record {$record} by {$user}";
        $this->log($message, [
            'action' => 'call_instance_deleted',
            'project_id' => $projectId,
            'record' => $record,
            'user' => $user
        ]);
    }

    /**
     * Log when project configuration is saved
     */
    public function logConfigSaved(
        int $projectId,
        string $user,
        array $summary = []
    ): void {
        $message = "Call Log configuration saved by {$user}";
        $params = array_merge([
            'action' => 'config_saved',
            'project_id' => $projectId,
            'user' => $user
        ], $summary);

        $this->log($message, $params);
    }
}
