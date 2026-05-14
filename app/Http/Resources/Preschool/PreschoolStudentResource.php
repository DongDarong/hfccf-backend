<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolStudent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolStudent */
class PreschoolStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studentCode' => $this->student_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => trim($this->first_name.' '.$this->last_name),
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            'guardianName' => $this->guardian_name,
            'guardianPhone' => $this->guardian_phone,
            'address' => $this->address,
            'status' => $this->status,
            'classesCount' => $this->whenLoaded('classes', fn () => $this->classes->count(), 0),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
