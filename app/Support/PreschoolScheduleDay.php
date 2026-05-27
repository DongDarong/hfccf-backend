<?php

namespace App\Support;

final class PreschoolScheduleDay
{
    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    public const SUNDAY = 7;

    /**
     * Keep the weekday contract explicit so the frontend can render a stable
     * timetable grid without guessing how days are stored in the database.
     *
     * @return array<int, int>
     */
    public static function values(): array
    {
        return [
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
            self::SATURDAY,
            self::SUNDAY,
        ];
    }
}
