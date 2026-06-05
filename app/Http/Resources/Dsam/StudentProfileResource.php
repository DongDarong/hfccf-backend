<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'student_id' => $this->student_id,
            'father' => [
                'name'       => $this->father_name,
                'dob'        => $this->father_dob?->toDateString(),
                'occupation' => $this->father_occupation,
                'income'     => $this->father_income,
                'phone'      => $this->father_phone,
                'status'     => $this->father_status,
            ],
            'mother' => [
                'name'       => $this->mother_name,
                'dob'        => $this->mother_dob?->toDateString(),
                'occupation' => $this->mother_occupation,
                'income'     => $this->mother_income,
                'phone'      => $this->mother_phone,
                'status'     => $this->mother_status,
            ],
            'guardian' => [
                'name'     => $this->guardian_name,
                'relation' => $this->guardian_relation,
                'phone'    => $this->guardian_phone,
            ],
            'household' => [
                'num_siblings'   => $this->num_siblings,
                'birth_order'    => $this->birth_order,
                'household_size' => $this->household_size,
                'monthly_income' => $this->monthly_income,
                'income_sources' => $this->income_sources,
            ],
            'housing' => [
                'type'            => $this->housing_type,
                'has_electricity' => $this->has_electricity,
                'has_clean_water' => $this->has_clean_water,
                'has_toilet'      => $this->has_toilet,
            ],
            'education' => [
                'distance_to_school' => $this->distance_to_school,
                'transport_mode'     => $this->transport_mode,
            ],
            'health' => [
                'status'              => $this->health_status,
                'disabilities'        => $this->disabilities,
                'has_health_insurance'=> $this->has_health_insurance,
                'vaccination_status'  => $this->vaccination_status,
            ],
            'notes'      => $this->notes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
