<?php

namespace App\Support;

final class PreschoolRelationshipType
{
    public const MOTHER = 'mother';

    public const FATHER = 'father';

    public const GUARDIAN = 'guardian';

    public const GRANDPARENT = 'grandparent';

    public const SIBLING = 'sibling';

    public const RELATIVE = 'relative';

    public const OTHER = 'other';

    public static function values(): array
    {
        return [
            self::MOTHER,
            self::FATHER,
            self::GUARDIAN,
            self::GRANDPARENT,
            self::SIBLING,
            self::RELATIVE,
            self::OTHER,
        ];
    }
}
