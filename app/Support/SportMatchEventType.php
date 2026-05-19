<?php

namespace App\Support;

final class SportMatchEventType
{
    public const GOAL = 'goal';
    public const ASSIST = 'assist';
    public const YELLOW_CARD = 'yellow_card';
    public const RED_CARD = 'red_card';
    public const SUBSTITUTION_IN = 'substitution_in';
    public const SUBSTITUTION_OUT = 'substitution_out';
    public const SUBSTITUTION = 'substitution';
    public const INJURY = 'injury';
    public const PENALTY_GOAL = 'penalty_goal';
    public const PENALTY_MISS = 'penalty_miss';
    public const PENALTY_MISSED = 'penalty_missed';
    public const OWN_GOAL = 'own_goal';
    public const EXTRA_TIME_GOAL = 'extra_time_goal';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::GOAL,
            self::ASSIST,
            self::YELLOW_CARD,
            self::RED_CARD,
            self::SUBSTITUTION_IN,
            self::SUBSTITUTION_OUT,
            self::SUBSTITUTION,
            self::INJURY,
            self::PENALTY_GOAL,
            self::PENALTY_MISS,
            self::PENALTY_MISSED,
            self::OWN_GOAL,
            self::EXTRA_TIME_GOAL,
        ];
    }

    public static function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            self::PENALTY_MISSED => self::PENALTY_MISS,
            self::SUBSTITUTION => self::SUBSTITUTION_OUT,
            default => $normalized,
        };
    }

    public static function isScoring(string $value): bool
    {
        return in_array(self::normalize($value), [
            self::GOAL,
            self::OWN_GOAL,
            self::PENALTY_GOAL,
            self::EXTRA_TIME_GOAL,
        ], true);
    }
}
