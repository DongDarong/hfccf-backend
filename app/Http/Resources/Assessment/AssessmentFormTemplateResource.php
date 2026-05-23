<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentFormTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'code'            => $this->code,
            'name'            => $this->name,
            'description'     => $this->description,
            'settings'        => $this->settings,
            'module'          => $this->module,
            'status'          => $this->status,
            'current_version' => $this->current_version,
            'created_by'      => $this->created_by,
            'updated_by'      => $this->updated_by,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
