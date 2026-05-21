<?php

namespace App\Support;

final class PreschoolGuardianGovernancePriority
{
    public const LOW = 'low';

    public const MEDIUM = 'medium';

    public const HIGH = 'high';

    public const URGENT = 'urgent';

    public static function values(): array
    {
        return [self::LOW, self::MEDIUM, self::HIGH, self::URGENT];
    }

    /**
     * Derive priority from severity and how many times this issue has recurred.
     * Critical issues with repeated recurrence escalate to urgent automatically.
     */
    public static function fromSeverityAndRecurrence(string $severity, int $recurrenceCount): string
    {
        return match ($severity) {
            'critical' => $recurrenceCount > 2 ? self::URGENT : self::HIGH,
            'warning' => $recurrenceCount > 1 ? self::HIGH : self::MEDIUM,
            default => self::LOW,
        };
    }

    /**
     * Stale age thresholds per severity in days.
     * An issue unresolved beyond this threshold is flagged as stale.
     */
    public static function staleThresholdDays(string $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'warning' => 7,
            default => 14,
        };
    }
}
