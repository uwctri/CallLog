<?php

namespace UWMadison\CallLog;

enum CallTemplateType: string
{
    case NEW = 'new';
    case REMINDER = 'reminder';
    case FOLLOWUP = 'followup';
    case MCV = 'mcv';
    case NTS = 'nts';
    case ADHOC = 'adhoc';
    case VISIT = 'visit';

    public static function isValid(string $template): bool
    {
        return self::tryFrom($template) !== null;
    }

    public static function getOptions(): array
    {
        return [
            self::NEW->value => 'New Entry',
            self::REMINDER->value => 'Reminder',
            self::FOLLOWUP->value => 'Follow Up',
            self::MCV->value => 'Missed / Cancelled Visit',
            self::NTS->value => 'Need to Schedule',
            self::ADHOC->value => 'Ad-hoc',
            self::VISIT->value => 'Scheduled Phone Visit',
        ];
    }
}
