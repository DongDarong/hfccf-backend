<?php

namespace App\Http\Resources\Preschool;

use App\Support\PreschoolGuardianGovernancePriority;
use App\Support\PreschoolGuardianGovernanceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreschoolGuardianGovernanceIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staleThresholdDays = PreschoolGuardianGovernancePriority::staleThresholdDays($this->severity);
        $daysSinceDetection = $this->detected_at
            ? (int) $this->detected_at->diffInDays(now())
            : 0;

        $isStale = PreschoolGuardianGovernanceStatus::isActive($this->status)
            && $daysSinceDetection >= $staleThresholdDays;

        return [
            'id' => $this->id,
            'issueType' => $this->issue_type,
            'issueKey' => $this->issue_key,
            'severity' => $this->severity,
            'priority' => $this->priority,
            'status' => $this->status,
            'studentId' => $this->student_id,
            'guardianId' => $this->guardian_id,
            'relationshipId' => $this->relationship_id,
            'assignedToUserId' => $this->assigned_to_user_id,
            'assignedToName' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo?->name),
            'detectedAt' => $this->detected_at?->toISOString(),
            'acknowledgedAt' => $this->acknowledged_at?->toISOString(),
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'dismissedAt' => $this->dismissed_at?->toISOString(),
            'recurrenceCount' => $this->recurrence_count,
            'latestSnapshot' => $this->latest_snapshot,
            'resolutionNotes' => $this->resolution_notes,
            'metadata' => $this->metadata,
            'isStale' => $isStale,
            'isRecurring' => $this->recurrence_count > 0,
            'daysSinceDetection' => $daysSinceDetection,
            'staleThresholdDays' => $staleThresholdDays,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
