<?php

namespace App\Support;

class SportEquipmentRequestStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const ISSUED = 'issued';

    public const RETURNED = 'returned';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::APPROVED,
            self::REJECTED,
            self::ISSUED,
            self::RETURNED,
        ];
    }
}
