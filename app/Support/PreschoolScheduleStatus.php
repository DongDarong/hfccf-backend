<?php

namespace App\Support;

final class PreschoolScheduleStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const ARCHIVED = 'archived';

    /**
     * Schedule entries stay intentionally small: active entries block overlap
     * conflicts, inactive entries remain visible, and archived entries keep
     * history without participating in timetable logic.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
            self::ARCHIVED,
        ];
    }
}
