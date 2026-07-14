<?php

namespace App\Support;

class SportEquipmentItemStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
        ];
    }
}
