<?php

namespace App\Support;

final class SportMatchSquadPlayerRole
{
    public const STARTER = 'starter';

    public const SUBSTITUTE = 'substitute';

    public const RESERVE = 'reserve';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::STARTER, self::SUBSTITUTE, self::RESERVE, self::UNAVAILABLE];
    }
}
