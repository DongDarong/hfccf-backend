<?php

namespace App\Support;

final class PreschoolAssessmentRating
{
    public const EMERGING = 'emerging';

    public const DEVELOPING = 'developing';

    public const PROFICIENT = 'proficient';

    public const ADVANCED = 'advanced';

    /**
     * Keep rating labels centralized so validation and future UI badges do not
     * drift apart across Preschool pages.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::EMERGING,
            self::DEVELOPING,
            self::PROFICIENT,
            self::ADVANCED,
        ];
    }
}
