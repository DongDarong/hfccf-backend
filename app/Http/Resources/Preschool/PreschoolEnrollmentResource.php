<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolEnrollmentApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolEnrollmentApplication */
class PreschoolEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'applicationCode' => $this->application_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => trim("{$this->first_name} {$this->last_name}"),
            'khmerName' => $this->khmer_name,
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            'placeOfBirth' => $this->place_of_birth,
            'nationality' => $this->nationality,
            'avatar' => $this->avatar,
            'requestedAcademicYearId' => $this->requested_academic_year_id,
            'requestedAcademicYear' => $this->requestedAcademicYear?->label,
            'requestedTermId' => $this->requested_term_id,
            'requestedTerm' => $this->requestedTerm?->name,
            'requestedLevel' => $this->requested_level,
            'preferredClassId' => $this->preferred_class_id,
            'preferredClass' => $this->preferredClass?->name,
            'requestedStartDate' => $this->requested_start_date?->toDateString(),
            'guardianName' => $this->guardian_name,
            'guardianRelationship' => $this->guardian_relationship,
            'guardianPhone' => $this->guardian_phone,
            'guardianEmail' => $this->guardian_email,
            'guardianAddress' => $this->guardian_address,
            'guardianCanPickup' => (bool) $this->guardian_can_pickup,
            'guardianIsEmergency' => (bool) $this->guardian_is_emergency,
            'status' => $this->status,
            'applicationDate' => $this->application_date?->toDateString(),
            'source' => $this->source,
            'adminNotes' => $this->admin_notes,
            'rejectionReason' => $this->rejection_reason,
            'waitlistReason' => $this->waitlist_reason,
            'reviewedByName' => $this->reviewedBy?->name,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'approvedByName' => $this->approvedBy?->name,
            'approvedAt' => $this->approved_at?->toISOString(),
            'enrolledByName' => $this->enrolledBy?->name,
            'enrolledAt' => $this->enrolled_at?->toISOString(),
            'enrolledStudentId' => $this->enrolled_student_id,
            'documents' => $this->whenLoaded('documents', fn () =>
                $this->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'documentType' => $doc->document_type,
                    'isRequired' => (bool) $doc->is_required,
                    'isReceived' => (bool) $doc->is_received,
                    'receivedDate' => $doc->received_date?->toDateString(),
                    'note' => $doc->note,
                ])
            ),
            'decisionLogs' => $this->whenLoaded('decisionLogs', fn () =>
                $this->decisionLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'fromStatus' => $log->from_status,
                    'toStatus' => $log->to_status,
                    'actorName' => $log->actor?->name,
                    'actorRole' => $log->actor_role,
                    'note' => $log->note,
                    'recordedAt' => $log->recorded_at?->toISOString(),
                ])
            ),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
