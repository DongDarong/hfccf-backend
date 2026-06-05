<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'student_id'       => $this->student_id,
            'academic_year_id' => $this->academic_year_id,
            'school_id'        => $this->school_id,
            'grade'            => $this->grade,
            'class_name'       => $this->class_name,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'academic_year'    => new AcademicYearResource($this->whenLoaded('academicYear')),
            'school'           => new SchoolResource($this->whenLoaded('school')),
            'recorded_by'      => $this->whenLoaded('recordedBy', fn () => [
                'id'   => $this->recordedBy->id,
                'name' => trim($this->recordedBy->first_name.' '.$this->recordedBy->last_name),
            ]),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
