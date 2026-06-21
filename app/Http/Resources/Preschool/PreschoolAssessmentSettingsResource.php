<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAssessmentSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolAssessmentSetting */
class PreschoolAssessmentSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'passing_score' => (int) $this->passing_score,
            'grading_scale_type' => $this->grading_scale_type,
            'weighting_enabled' => (bool) $this->weighting_enabled,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
