<?php

namespace App\Http\Resources\Scholarship;

use App\Models\ScholarshipApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScholarshipApplication */
class ScholarshipApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $application = $this->resource;

        return [
            'id' => $application->id,
            'applicationCode' => $application->application_code,
            'scholarshipType' => $application->scholarship_type,
            'requestedAmount' => (float) $application->requested_amount,
            'academicYear' => $application->academic_year,
            'submissionDate' => $application->submission_date?->toDateString(),
            'applicationStatus' => $application->application_status,
            'assignedReviewerUserId' => $application->assigned_reviewer_user_id,
            'assignedReviewerName' => $application->relationLoaded('assignedReviewer')
                ? trim(($application->assignedReviewer->first_name ?? '').' '.($application->assignedReviewer->last_name ?? ''))
                : null,
            'reviewedAt' => $application->reviewed_at?->toISOString(),
            'approvedAt' => $application->approved_at?->toISOString(),
            'rejectedAt' => $application->rejected_at?->toISOString(),
            'rejectionReason' => $application->rejection_reason,
            'notes' => $application->notes,
            'student' => $application->relationLoaded('student') ? new ScholarshipStudentResource($application->student) : null,
            'reviews' => $application->relationLoaded('reviews') ? ScholarshipReviewResource::collection($application->reviews)->resolve($request) : [],
            'statusHistories' => $application->relationLoaded('statusHistories')
                ? $application->statusHistories->map(static function ($history): array {
                    return [
                        'id' => $history->id,
                        'previousStatus' => $history->previous_status,
                        'newStatus' => $history->new_status,
                        'changedByUserId' => $history->changed_by_user_id,
                        'note' => $history->note,
                        'createdAt' => $history->created_at?->toISOString(),
                    ];
                })->values()->all()
                : [],
            'createdAt' => $application->created_at?->toISOString(),
            'updatedAt' => $application->updated_at?->toISOString(),
        ];
    }
}
