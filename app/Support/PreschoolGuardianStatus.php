<?php

namespace App\Support;

final class PreschoolGuardianStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const ARCHIVED = 'archived';

    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
            self::ARCHIVED,
        ];
    }
}
