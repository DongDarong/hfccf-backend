<?php

namespace App\Support;

final class SportMatchSquadStatus
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const LOCKED = 'locked';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::DRAFT, self::SUBMITTED, self::APPROVED, self::LOCKED];
    }
}
