<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolHealthSeverityLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolHealthSeverityLevel */
class PreschoolHealthSeverityLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'priority' => (int) $this->priority,
            'color' => $this->color,
            'requires_acknowledgment' => (bool) $this->requires_acknowledgment,
            'triggers_notification' => (bool) $this->triggers_notification,
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
