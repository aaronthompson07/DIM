<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * US federal public holidays (calendar dates / Monday rules) and “season” windows for UI theming.
 * Windows: 4 days before through 4 days after each holiday (9 days), inclusive.
 */
final class UsFederalHolidays
{
    private const WINDOW_DAYS = 4;

    /** Lower = wins ties when distances are equal. */
    private const PRIORITY = [
        'christmas' => 1,
        'new_year' => 2,
        'thanksgiving' => 3,
        'independence' => 4,
        'juneteenth' => 5,
        'memorial' => 6,
        'labor' => 7,
        'mlk' => 8,
        'presidents' => 9,
        'columbus' => 10,
        'veterans' => 11,
    ];

    /**
     * @return list<array{key: string, name: string, date: string}>
     */
    public static function holidaysForYear(int $year): array
    {
        if ($year < 1970 || $year > 2100) {
            return [];
        }

        return [
            ['key' => 'new_year', 'name' => "New Year's Day", 'date' => sprintf('%04d-01-01', $year)],
            ['key' => 'mlk', 'name' => 'Martin Luther King Jr. Day', 'date' => self::nthWeekdayOfMonth($year, 1, 1, 3)],
            ['key' => 'presidents', 'name' => "Washington's Birthday", 'date' => self::nthWeekdayOfMonth($year, 2, 1, 3)],
            ['key' => 'memorial', 'name' => 'Memorial Day', 'date' => self::lastWeekdayOfMonth($year, 5, 1)],
            ['key' => 'juneteenth', 'name' => 'Juneteenth National Independence Day', 'date' => sprintf('%04d-06-19', $year)],
            ['key' => 'independence', 'name' => 'Independence Day', 'date' => sprintf('%04d-07-04', $year)],
            ['key' => 'labor', 'name' => 'Labor Day', 'date' => self::nthWeekdayOfMonth($year, 9, 1, 1)],
            ['key' => 'columbus', 'name' => 'Columbus Day', 'date' => self::nthWeekdayOfMonth($year, 10, 1, 2)],
            ['key' => 'veterans', 'name' => 'Veterans Day', 'date' => sprintf('%04d-11-11', $year)],
            ['key' => 'thanksgiving', 'name' => 'Thanksgiving Day', 'date' => self::nthWeekdayOfMonth($year, 11, 4, 4)],
            ['key' => 'christmas', 'name' => 'Christmas Day', 'date' => sprintf('%04d-12-25', $year)],
        ];
    }

    /**
     * If $todayYmd falls within ±WINDOW_DAYS of a holiday, return the winning skin key and label.
     *
     * @return array{skin: string, holiday_name: string, holiday_date: string, days_from_holiday: int}|null
     */
    public static function activeSeasonForDate(string $todayYmd): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $todayYmd)) {
            return null;
        }
        $y = (int) substr($todayYmd, 0, 4);
        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ([$y - 1, $y, $y + 1] as $year) {
            foreach (self::holidaysForYear($year) as $h) {
                $hd = $h['date'];
                if (!self::isWithinWindow($todayYmd, $hd, self::WINDOW_DAYS)) {
                    continue;
                }
                $dist = abs(self::calendarDaysBetween($todayYmd, $hd));
                $pri = self::PRIORITY[$h['key']] ?? 50;
                /** Closer to the holiday beats ties; then lower priority number wins. */
                $score = $dist * 100 + $pri;
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $signed = self::calendarDaysBetweenSigned($todayYmd, $hd);
                    $best = [
                        'skin' => $h['key'],
                        'holiday_name' => $h['name'],
                        'holiday_date' => $hd,
                        'days_from_holiday' => $signed,
                    ];
                }
            }
        }

        return $best;
    }

    private static function isWithinWindow(string $todayYmd, string $holidayYmd, int $radius): bool
    {
        return abs(self::calendarDaysBetween($todayYmd, $holidayYmd)) <= $radius;
    }

    private static function calendarDaysBetween(string $a, string $b): int
    {
        return abs(self::calendarDaysBetweenSigned($a, $b));
    }

    /** Signed calendar days: $a minus $b (e.g. today minus holiday). */
    private static function calendarDaysBetweenSigned(string $a, string $b): int
    {
        $da = new DateTimeImmutable($a . ' 12:00:00', new DateTimeZone('UTC'));
        $db = new DateTimeImmutable($b . ' 12:00:00', new DateTimeZone('UTC'));

        return (int) floor(($da->getTimestamp() - $db->getTimestamp()) / 86400);
    }

    /**
     * @param int $weekdayN PHP `N`: 1 = Monday … 7 = Sunday
     * @param int $nth       1 = first such weekday in month
     */
    private static function nthWeekdayOfMonth(int $year, int $month, int $weekdayN, int $nth): string
    {
        $d = new DateTimeImmutable(sprintf('%04d-%02d-01 12:00:00', $year, $month), new DateTimeZone('UTC'));
        while ((int) $d->format('N') !== $weekdayN) {
            $d = $d->add(new DateInterval('P1D'));
        }
        for ($i = 1; $i < $nth; $i++) {
            $d = $d->add(new DateInterval('P7D'));
        }

        return $d->format('Y-m-d');
    }

    /** Last Monday in month (Memorial Day). @param int $weekdayN PHP `N` (1 = Monday) */
    private static function lastWeekdayOfMonth(int $year, int $month, int $weekdayN): string
    {
        $d = new DateTimeImmutable(sprintf('%04d-%02d-01 12:00:00', $year, $month), new DateTimeZone('UTC'));
        $d = $d->modify('last day of this month');
        while ((int) $d->format('N') !== $weekdayN) {
            $d = $d->sub(new DateInterval('P1D'));
        }

        return $d->format('Y-m-d');
    }
}
