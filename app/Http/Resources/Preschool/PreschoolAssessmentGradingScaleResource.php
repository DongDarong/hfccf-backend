<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAssessmentGradingScale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolAssessmentGradingScale */
class PreschoolAssessmentGradingScaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'grade' => $this->grade,
            'minimum_score' => (float) $this->minimum_score,
            'maximum_score' => (float) $this->maximum_score,
            'color' => $this->color,
            'sort_order' => (int) $this->sort_order,
            'is_passing' => (bool) $this->is_passing,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
