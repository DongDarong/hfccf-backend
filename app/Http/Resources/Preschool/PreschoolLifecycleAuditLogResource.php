<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolLifecycleAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolLifecycleAuditLog */
class PreschoolLifecycleAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actorUserId' => $this->actor_user_id,
            'actorRole' => $this->actor_role,
            'actionType' => $this->action_type,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'academicYearId' => $this->academic_year_id,
            'termId' => $this->term_id,
            'reportPeriodId' => $this->report_period_id,
            'previousState' => $this->previous_state,
            'newState' => $this->new_state,
            'overrideReason' => $this->override_reason,
            'lockCode' => $this->lock_code,
            'lockReason' => $this->lock_reason,
            'requestContext' => $this->request_context,
            'createdAt' => $this->created_at?->toISOString(),
            'actor' => $this->whenLoaded('actor', fn (): array => [
                'id' => $this->actor?->id,
                'firstName' => $this->actor?->first_name,
                'lastName' => $this->actor?->last_name,
                'username' => $this->actor?->username,
                'email' => $this->actor?->email,
                'roleCode' => $this->actor?->role_code,
            ]),
            'reportPeriod' => $this->whenLoaded('reportPeriod', fn (): array => [
                'id' => $this->reportPeriod?->id,
                'periodLabel' => $this->reportPeriod?->period_label,
                'status' => $this->reportPeriod?->status,
            ]),
        ];
    }
}
