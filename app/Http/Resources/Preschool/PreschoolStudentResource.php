<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolStudent;
use App\Support\PreschoolGuardianSnapshotService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolStudent */
class PreschoolStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve the underlying model so the canonical guardian snapshot
        // always works with the real PreschoolStudent instance, not the
        // JsonResource wrapper. This keeps normalized guardian data stable
        // without breaking legacy student CRUD responses.
        $guardianSnapshot = app(PreschoolGuardianSnapshotService::class)->preferredGuardianSnapshot($this->resource);

        return [
            'id' => $this->id,
            'studentCode' => $this->student_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => trim($this->first_name.' '.$this->last_name),
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            // Prefer the normalized guardian snapshot so the compatibility
            // columns do not override active relationships.
            'guardianName' => $guardianSnapshot['guardianName'] ?? $this->guardian_name,
            'guardianPhone' => $guardianSnapshot['guardianPhone'] ?? $this->guardian_phone,
            'guardianSource' => $guardianSnapshot['source'] ?? 'legacy',
            'address' => $this->address,
            'status' => $this->status,
            'classesCount' => $this->whenLoaded('classes', fn () => $this->classes->count(), 0),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
