<?php

namespace App\Support;

final class SportMembershipStatus
{
    public const ACTIVE = 'active';

    public const LOANED = 'loaned';

    public const INACTIVE = 'inactive';

    public const SUSPENDED = 'suspended';

    public const RELEASED = 'released';

    public const EXPIRED = 'expired';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::LOANED,
            self::INACTIVE,
            self::SUSPENDED,
            self::RELEASED,
            self::EXPIRED,
        ];
    }
}
