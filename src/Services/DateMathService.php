<?php

namespace UWMadison\CallLog\Services;

class DateMathService
{
    private int $dateMathCutoff = 5;

    public static array $defaultHolidayMap = [
        'new_years_day' => "New Year's Day (Jan 1)",
        'mlk_day' => "Martin Luther King Jr. Day (3rd Mon in Jan)",
        'memorial_day' => "Memorial Day (Last Mon in May)",
        'juneteenth' => "Juneteenth National Independence Day (Jun 19)",
        'independence_day' => "Independence Day (Jul 4)",
        'labor_day' => "Labor Day (1st Mon in Sep)",
        'veterans_day' => "Veterans Day (Nov 11)",
        'thanksgiving' => "Thanksgiving Day (4th Thu in Nov)",
        'day_after_thanksgiving' => "Day After Thanksgiving (Fri after Thanksgiving)",
        'christmas_eve' => "Christmas Eve (Dec 24)",
        'christmas_day' => "Christmas Day (Dec 25)",
        'new_years_eve' => "New Year's Eve (Dec 31)",
    ];

    /**
     * Calculates a business-day adjusted date string based on an initial date, operation, and days.
     *
     * @param string $date Input date string (e.g. 'YYYY-MM-DD')
     * @param string $operation Addition ('+') or subtraction ('-')
     * @param int $days Number of days to offset
     * @param array|null $enabledHolidays Array of enabled standard holiday keys
     * @param array|null $customDates Array of custom holiday date strings ('MM-DD' or 'YYYY-MM-DD')
     * @param array|null $customNames Array of custom holiday name descriptions
     * @return string Adjusted date formatted as 'YYYY-MM-DD'
     */
    public function dateMath(
        string $date,
        string $operation,
        int $days,
        ?array $enabledHolidays = null,
        ?array $customDates = null,
        ?array $customNames = null
    ): string {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return date('Y-m-d');
        }

        $op = ($operation === '-') ? '-' : '+';
        $days = max(0, $days);

        $calculatedDate = date('Y-m-d', strtotime("{$op}{$days} days", $timestamp));

        // Skip weekend/holiday adjustments if offsetting beyond cutoff
        if ($days > $this->dateMathCutoff) {
            return $calculatedDate;
        }

        return $this->adjustForWeekendsAndHolidays($calculatedDate, $op, $enabledHolidays, $customDates, $customNames);
    }

    /**
     * Adjusts a target date so it does not land on a weekend or calculated holiday.
     *
     * @param string $date
     * @param string $operation
     * @param array|null $enabledHolidays
     * @param array|null $customDates
     * @param array|null $customNames
     * @return string
     */
    private function adjustForWeekendsAndHolidays(
        string $date,
        string $operation,
        ?array $enabledHolidays = null,
        ?array $customDates = null,
        ?array $customNames = null
    ): string {
        $currentTs = strtotime($date);
        $step = ($operation === '-') ? '-1 day' : '+1 day';
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $year = (int)date('Y', $currentTs);
            $fullDate = date('Y-m-d', $currentTs);
            $dayOfWeek = (int)date('N', $currentTs); // 1 = Mon, 6 = Sat, 7 = Sun

            $holidays = $this->getHolidaysForYear($year, $enabledHolidays, $customDates, $customNames);

            $isWeekend = ($dayOfWeek >= 6);
            $isHoliday = isset($holidays[$fullDate]);

            if (!$isWeekend && !$isHoliday) {
                break;
            }

            $currentTs = strtotime($step, $currentTs);
            $attempt++;
        }

        return date('Y-m-d', $currentTs);
    }

    /**
     * Generates labeled holiday dates for a specific year based on enabled standard holidays and custom site closures.
     *
     * @param int $year
     * @param array|null $enabledHolidays
     * @param array|null $customDates
     * @param array|null $customNames
     * @return array<string, string> Map of 'YYYY-MM-DD' => Holiday Name
     */
    public function getHolidaysForYear(
        int $year,
        ?array $enabledHolidays = null,
        ?array $customDates = null,
        ?array $customNames = null
    ): array {
        $enabled = is_array($enabledHolidays) ? $enabledHolidays : array_keys(self::$defaultHolidayMap);
        $enabledMap = array_flip($enabled);

        $holidays = [];

        // Fixed Standard Holidays
        $fixedMap = [
            'new_years_day' => [sprintf('%04d-01-01', $year), "New Year's Day"],
            'juneteenth' => [sprintf('%04d-06-19', $year), "Juneteenth National Independence Day"],
            'independence_day' => [sprintf('%04d-07-04', $year), "Independence Day"],
            'veterans_day' => [sprintf('%04d-11-11', $year), "Veterans Day"],
            'christmas_eve' => [sprintf('%04d-12-24', $year), "Christmas Eve"],
            'christmas_day' => [sprintf('%04d-12-25', $year), "Christmas Day"],
            'new_years_eve' => [sprintf('%04d-12-31', $year), "New Year's Eve"],
        ];

        foreach ($fixedMap as $key => [$dateStr, $label]) {
            if (isset($enabledMap[$key])) {
                $holidays[$dateStr] = $label;
            }
        }

        // Floating Standard Holidays
        $thanksgivingDate = date('Y-m-d', strtotime("fourth thursday of november {$year}"));

        $floatingMap = [
            'mlk_day' => [date('Y-m-d', strtotime("third monday of january {$year}")), "Martin Luther King Jr. Day"],
            'memorial_day' => [date('Y-m-d', strtotime("last monday of may {$year}")), "Memorial Day"],
            'labor_day' => [date('Y-m-d', strtotime("first monday of september {$year}")), "Labor Day"],
            'thanksgiving' => [$thanksgivingDate, "Thanksgiving Day"],
            'day_after_thanksgiving' => [date('Y-m-d', strtotime("{$thanksgivingDate} +1 day")), "Day After Thanksgiving"],
        ];

        foreach ($floatingMap as $key => [$dateStr, $label]) {
            if (isset($enabledMap[$key])) {
                $holidays[$dateStr] = $label;
            }
        }

        // Custom Site Closures
        if (is_array($customDates)) {
            foreach ($customDates as $i => $cDate) {
                $cDate = trim((string)$cDate);
                if (empty($cDate)) continue;

                $cName = trim((string)($customNames[$i] ?? 'Custom Site Closure'));

                if (preg_match('/^\d{2}-\d{2}$/', $cDate)) {
                    $holidays[sprintf('%04d-%s', $year, $cDate)] = $cName;
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cDate)) {
                    if (str_starts_with($cDate, (string)$year)) {
                        $holidays[$cDate] = $cName;
                    }
                }
            }
        }

        return $holidays;
    }
}
