<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'name_kh'    => $this->name_kh,
            'type'       => $this->type,
            'logo'       => $this->logo,
            'province'   => $this->province,
            'address'    => $this->address,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'settings'   => $this->settings,
            'is_active'  => $this->is_active,
            'schools'        => SchoolResource::collection($this->whenLoaded('schools')),
            'academic_years' => AcademicYearResource::collection($this->whenLoaded('academicYears')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
