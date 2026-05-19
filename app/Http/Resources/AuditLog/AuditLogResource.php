<?php

namespace App\Http\Resources\AuditLog;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actorUserId' => $this->actor_user_id,
            'domain' => $this->domain,
            'action' => $this->action,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'entityLabel' => $this->entity_label,
            'oldValues' => $this->old_values,
            'newValues' => $this->new_values,
            'metadata' => $this->metadata,
            'ipAddress' => $this->ip_address,
            'userAgent' => $this->user_agent,
            'createdAt' => $this->created_at?->toISOString(),
            'actor' => $this->whenLoaded('actor', fn (): array => [
                'id' => $this->actor?->id,
                'firstName' => $this->actor?->first_name,
                'lastName' => $this->actor?->last_name,
                'username' => $this->actor?->username,
                'email' => $this->actor?->email,
                'roleCode' => $this->actor?->role_code,
            ]),
        ];
    }
}
