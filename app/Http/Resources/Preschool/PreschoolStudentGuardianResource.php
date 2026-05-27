<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolStudentGuardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolStudentGuardian */
class PreschoolStudentGuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studentId' => $this->student_id,
            'guardianId' => $this->guardian_id,
            'relationshipType' => $this->relationship_type,
            'isPrimary' => (bool) $this->is_primary,
            'canPickup' => (bool) $this->can_pickup,
            'emergencyPriority' => $this->emergency_priority,
            'status' => $this->status,
            'startsAt' => $this->starts_at?->toDateString(),
            'endsAt' => $this->ends_at?->toDateString(),
            'notes' => $this->notes,
            'guardianName' => $this->guardian?->full_name,
            'guardianPhone' => $this->guardian?->phone,
            'guardianSecondaryPhone' => $this->guardian?->secondary_phone,
            'guardianEmail' => $this->guardian?->email,
            'guardian' => $this->whenLoaded('guardian', function () use ($request): array {
                return PreschoolGuardianResource::make($this->guardian)->resolve($request);
            }),
            'createdByUserId' => $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
