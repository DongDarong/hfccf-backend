<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolGuardianCommunication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolGuardianCommunication */
class PreschoolGuardianCommunicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studentId' => $this->student_id,
            'studentName' => trim(($this->student?->first_name ?? '').' '.($this->student?->last_name ?? '')),
            'guardianId' => $this->guardian_id,
            'guardianName' => $this->guardian?->full_name,
            'sourceType' => $this->source_type,
            'sourceId' => $this->source_id,
            'communicationType' => $this->communication_type,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'message' => $this->message,
            'severity' => $this->severity,
            'status' => $this->status,
            'sentAt' => $this->sent_at?->toISOString(),
            'acknowledgedAt' => $this->acknowledged_at?->toISOString(),
            'failedAt' => $this->failed_at?->toISOString(),
            'createdBy' => $this->created_by,
            'createdByName' => trim(($this->creator?->first_name ?? '').' '.($this->creator?->last_name ?? '')),
            'sourceLabel' => $this->sourceLabel(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function sourceLabel(): string
    {
        return match ($this->source_type) {
            'health_alert' => 'Health alert',
            'attendance' => 'Attendance',
            'assessment' => 'Assessment',
            'enrollment' => 'Enrollment',
            'governance_issue' => 'Guardian governance',
            'manual_note' => 'Manual note',
            default => $this->source_type ?? '',
        };
    }
}
