<?php

namespace UWMadison\CallLog;

class CallItemDTO
{
    public string $id;
    public string $name;
    public string $template;
    public string $eventId;
    public array $instances;
    public int $voiceMails;
    public int $hideAfterAttempt;
    public bool $complete;
    public ?string $completedBy;
    public ?string $start;
    public ?string $end;
    public ?string $contactOn;
    public ?string $reported;
    public ?string $reporter;
    public ?string $reason;
    public ?string $initNotes;
    public ?string $appt;
    public ?string $created;
    public ?string $load;
    public ?int $expire;
    public ?string $callStarted;
    public ?string $callStartedBy;
    public array $noCallsToday;

    public function __construct(array $data = [])
    {
        $this->id = (string)($data['id'] ?? '');
        $this->name = (string)($data['name'] ?? '');
        $this->template = (string)($data['template'] ?? '');
        $this->eventId = (string)($data['event_id'] ?? '');
        $this->instances = is_array($data['instances'] ?? null) ? $data['instances'] : [];
        $this->voiceMails = (int)($data['voiceMails'] ?? 0);
        $this->hideAfterAttempt = (int)($data['hideAfterAttempt'] ?? 9999);
        $this->complete = (bool)($data['complete'] ?? false);
        $this->completedBy = isset($data['completedBy']) ? (string)$data['completedBy'] : null;
        $this->start = isset($data['start']) ? (string)$data['start'] : null;
        $this->end = isset($data['end']) ? (string)$data['end'] : null;
        $this->contactOn = isset($data['contactOn']) ? (string)$data['contactOn'] : null;
        $this->reported = isset($data['reported']) ? (string)$data['reported'] : null;
        $this->reporter = isset($data['reporter']) ? (string)$data['reporter'] : null;
        $this->reason = isset($data['reason']) ? (string)$data['reason'] : null;
        $this->initNotes = isset($data['initNotes']) ? (string)$data['initNotes'] : null;
        $this->appt = isset($data['appt']) ? (string)$data['appt'] : null;
        $this->created = isset($data['created']) ? (string)$data['created'] : null;
        $this->load = isset($data['load']) ? (string)$data['load'] : null;
        $this->expire = isset($data['expire']) ? (int)$data['expire'] : null;
        $this->callStarted = isset($data['callStarted']) ? (string)$data['callStarted'] : null;
        $this->callStartedBy = isset($data['callStartedBy']) ? (string)$data['callStartedBy'] : null;

        $noCalls = $data['noCallsToday'] ?? [];
        if (!is_array($noCalls)) {
            $noCalls = empty($noCalls) ? [] : [$noCalls];
        }
        $this->noCallsToday = $noCalls;
    }

    public function toArray(): array
    {
        $array = [
            'id' => $this->id,
            'name' => $this->name,
            'template' => $this->template,
            'event_id' => $this->eventId,
            'instances' => $this->instances,
            'voiceMails' => $this->voiceMails,
            'hideAfterAttempt' => $this->hideAfterAttempt,
            'complete' => $this->complete,
            'noCallsToday' => $this->noCallsToday,
        ];

        if ($this->completedBy !== null) $array['completedBy'] = $this->completedBy;
        if ($this->start !== null) $array['start'] = $this->start;
        if ($this->end !== null) $array['end'] = $this->end;
        if ($this->contactOn !== null) $array['contactOn'] = $this->contactOn;
        if ($this->reported !== null) $array['reported'] = $this->reported;
        if ($this->reporter !== null) $array['reporter'] = $this->reporter;
        if ($this->reason !== null) $array['reason'] = $this->reason;
        if ($this->initNotes !== null) $array['initNotes'] = $this->initNotes;
        if ($this->appt !== null) $array['appt'] = $this->appt;
        if ($this->created !== null) $array['created'] = $this->created;
        if ($this->load !== null) $array['load'] = $this->load;
        if ($this->expire !== null) $array['expire'] = $this->expire;
        if ($this->callStarted !== null) $array['callStarted'] = $this->callStarted;
        if ($this->callStartedBy !== null) $array['callStartedBy'] = $this->callStartedBy;

        return $array;
    }
}
