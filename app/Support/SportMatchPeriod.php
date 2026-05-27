<?php

namespace App\Support;

final class SportMatchPeriod
{
    public const FIRST_HALF = 'first_half';
    public const HALF_TIME = 'halftime';
    public const SECOND_HALF = 'second_half';
    public const EXTRA_TIME = 'extra_time';
    public const PENALTY_SHOOTOUT = 'penalty_shootout';
    public const FINAL = 'final';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::FIRST_HALF,
            self::HALF_TIME,
            self::SECOND_HALF,
            self::EXTRA_TIME,
            self::PENALTY_SHOOTOUT,
            self::FINAL,
        ];
    }
}
