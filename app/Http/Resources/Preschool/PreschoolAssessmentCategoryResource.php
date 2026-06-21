<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAssessmentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolAssessmentCategory */
class PreschoolAssessmentCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->trashed() ? 'archived' : ((bool) $this->is_active ? 'active' : 'inactive'),
            'sortOrder' => $this->sort_order,
            'isActive' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
