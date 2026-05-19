<?php

namespace App\Support;

final class PreschoolAssessmentStatus
{
    public const DRAFT = 'draft';

    public const FINALIZED = 'finalized';

    public const ARCHIVED = 'archived';

    /**
     * Keep the lifecycle explicit so the API and frontend stay aligned when
     * draft-only editing and finalization rules evolve.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::DRAFT, self::FINALIZED, self::ARCHIVED];
    }
}
