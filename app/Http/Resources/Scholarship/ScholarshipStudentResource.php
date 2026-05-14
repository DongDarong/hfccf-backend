<?php

namespace App\Http\Resources\Scholarship;

use App\Models\ScholarshipStudent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScholarshipStudent */
class ScholarshipStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $student = $this->resource;
        $fullName = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));

        return [
            'id' => $student->id,
            'studentCode' => $student->student_code,
            'firstName' => $student->first_name,
            'lastName' => $student->last_name,
            'fullName' => $fullName,
            'gender' => $student->gender,
            'dateOfBirth' => $student->date_of_birth?->toDateString(),
            'phone' => $student->phone,
            'email' => $student->email,
            'schoolName' => $student->school_name,
            'gradeLevel' => $student->grade_level,
            'guardianName' => $student->guardian_name,
            'guardianPhone' => $student->guardian_phone,
            'address' => $student->address,
            'status' => $student->status,
            'notes' => $student->notes,
            'applicationsCount' => $student->relationLoaded('applications')
                ? $student->applications->count()
                : ($student->applications_count ?? null),
            'createdAt' => $student->created_at?->toISOString(),
            'updatedAt' => $student->updated_at?->toISOString(),
        ];
    }
}
