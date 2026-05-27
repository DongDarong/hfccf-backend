<?php

namespace App\Support;

final class SportMatchEligibilityStatus
{
    public const ELIGIBLE = 'eligible';

    public const PENDING = 'pending';

    public const INJURED = 'injured';

    public const SUSPENDED = 'suspended';

    public const INACTIVE = 'inactive';

    public const RELEASED = 'released';

    public const ARCHIVED = 'archived';

    public const NOT_MEMBER = 'not_member';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::ELIGIBLE,
            self::PENDING,
            self::INJURED,
            self::SUSPENDED,
            self::INACTIVE,
            self::RELEASED,
            self::ARCHIVED,
            self::NOT_MEMBER,
        ];
    }
}
