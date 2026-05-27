<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolGuardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolGuardian */
class PreschoolGuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->full_name,
            'phone' => $this->phone,
            'secondaryPhone' => $this->secondary_phone,
            'email' => $this->email,
            'address' => $this->address,
            'occupation' => $this->occupation,
            'nationalId' => $this->national_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'relationshipsCount' => (int) ($this->relationships_count ?? 0),
            'activeRelationshipsCount' => (int) ($this->active_relationships_count ?? 0),
            'createdByUserId' => $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
