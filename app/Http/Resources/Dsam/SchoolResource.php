<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'name'            => $this->name,
            'name_kh'         => $this->name_kh,
            'province'        => $this->province,
            'district'        => $this->district,
            'commune'         => $this->commune,
            'address'         => $this->address,
            'principal_name'  => $this->principal_name,
            'phone'           => $this->phone,
            'is_active'       => $this->is_active,
            'organization'    => new OrganizationResource($this->whenLoaded('organization')),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
