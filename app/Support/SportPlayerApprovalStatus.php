<?php

namespace App\Support;

final class SportPlayerApprovalStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::PENDING, self::APPROVED, self::REJECTED];
    }
}
