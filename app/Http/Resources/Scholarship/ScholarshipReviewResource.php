<?php

namespace App\Http\Resources\Scholarship;

use App\Models\ScholarshipReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScholarshipReview */
class ScholarshipReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;

        return [
            'id' => $review->id,
            'applicationId' => $review->application_id,
            'reviewerUserId' => $review->reviewer_user_id,
            'reviewerName' => $review->relationLoaded('reviewer')
                ? trim(($review->reviewer->first_name ?? '').' '.($review->reviewer->last_name ?? ''))
                : null,
            'score' => $review->score,
            'recommendation' => $review->recommendation,
            'reviewNote' => $review->review_note,
            'reviewedAt' => $review->reviewed_at?->toISOString(),
            'createdAt' => $review->created_at?->toISOString(),
            'updatedAt' => $review->updated_at?->toISOString(),
        ];
    }
}
