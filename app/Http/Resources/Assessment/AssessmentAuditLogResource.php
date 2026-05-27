<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'event'       => $this->action,
            'action'      => $this->action,
            'actor'       => $this->whenLoaded('user', fn () => [
                'id'    => $this->user?->id,
                'name'  => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'description' => $this->entity_label ?? $this->action,
            'entity_type' => $this->entity_type,
            'entity_id'   => $this->entity_id,
            'entity_label'=> $this->entity_label,
            'old_value'   => $this->old_value,
            'new_value'   => $this->new_value,
            'meta'        => $this->meta,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
