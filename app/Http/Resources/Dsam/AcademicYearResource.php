<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicYearResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organization_id,
            'name'            => $this->name,
            'start_date'      => $this->start_date?->toDateString(),
            'end_date'        => $this->end_date?->toDateString(),
            'is_current'      => $this->is_current,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
