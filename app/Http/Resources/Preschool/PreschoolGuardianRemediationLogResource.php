<?php

namespace App\Http\Resources\Preschool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreschoolGuardianRemediationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issueType' => $this->issue_type,
            'issueKey' => $this->issue_key,
            'studentId' => $this->student_id,
            'guardianId' => $this->guardian_id,
            'relatedGuardianId' => $this->related_guardian_id,
            'relationshipId' => $this->relationship_id,
            'action' => $this->action,
            'beforeSnapshot' => $this->before_snapshot,
            'afterSnapshot' => $this->after_snapshot,
            'notes' => $this->notes,
            'performedByUserId' => $this->performed_by_user_id,
            'performedByName' => $this->whenLoaded('performedBy', fn () => $this->performedBy?->name),
            'performedAt' => $this->performed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
