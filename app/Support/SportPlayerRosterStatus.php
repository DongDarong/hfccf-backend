<?php

namespace App\Support;

final class SportPlayerRosterStatus
{
    public const ACTIVE = 'active';

    public const INJURED = 'injured';

    public const SUSPENDED = 'suspended';

    public const INACTIVE = 'inactive';

    public const RELEASED = 'released';

    public const GRADUATED = 'graduated';

    public const ARCHIVED = 'archived';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::INJURED,
            self::SUSPENDED,
            self::INACTIVE,
            self::RELEASED,
            self::GRADUATED,
            self::ARCHIVED,
        ];
    }
}
