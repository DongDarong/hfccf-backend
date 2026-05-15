<?php

namespace App\Http\Resources\English;

use App\Models\EnglishStudent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EnglishStudent */
class EnglishStudentResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status,
            'classIds' => $this->whenLoaded('classes', fn (): array => $this->classes->pluck('id')->values()->all()),
            'classes' => $this->whenLoaded('classes', fn (): array => $this->classes->map(static function ($class): array {
                return [
                    'id' => $class->id,
                    'classCode' => $class->class_code,
                    'name' => $class->name,
                ];
            })->values()->all()),
            'classesCount' => $this->classes_count ?? $this->whenCounted('classes'),
            'submissionsCount' => $this->submissions_count ?? $this->whenCounted('submissions'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
