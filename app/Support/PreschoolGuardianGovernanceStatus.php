<?php

namespace App\Support;

final class PreschoolGuardianGovernanceStatus
{
    public const DETECTED = 'detected';

    public const ACKNOWLEDGED = 'acknowledged';

    public const ASSIGNED = 'assigned';

    public const IN_REVIEW = 'in_review';

    public const RESOLVED = 'resolved';

    public const DISMISSED = 'dismissed';

    public const ACTIVE_STATES = [
        self::DETECTED,
        self::ACKNOWLEDGED,
        self::ASSIGNED,
        self::IN_REVIEW,
    ];

    public static function values(): array
    {
        return [
            self::DETECTED,
            self::ACKNOWLEDGED,
            self::ASSIGNED,
            self::IN_REVIEW,
            self::RESOLVED,
            self::DISMISSED,
        ];
    }

    public static function isActive(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATES, true);
    }
}
