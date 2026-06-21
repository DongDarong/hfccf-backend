<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolVaccinationCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolVaccinationCategory */
class PreschoolVaccinationCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'recommended_age_months' => $this->recommended_age_months,
            'is_required' => (bool) $this->is_required,
            'is_active' => (bool) $this->is_active,
            'status' => $this->deleted_at ? 'archived' : ($this->is_active ? 'active' : 'archived'),
            'sort_order' => (int) $this->sort_order,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
