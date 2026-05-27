<?php

namespace App\Services;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipStatusHistory;
use App\Models\ScholarshipStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ScholarshipService
{
    public function dashboardSummary(User $user): array
    {
        $teacherScope = $this->teacherApplicationScope($user);
        $studentQuery = ScholarshipStudent::query();
        $applicationQuery = ScholarshipApplication::query();

        if ($user->role_code === 'teacher-scholarship') {
            $applicationQuery = $teacherScope($applicationQuery);
            $studentQuery->whereHas('applications', $teacherScope);
        }

        $applications = $applicationQuery->get();
        $students = $studentQuery->get();

        return [
            'summary' => [
                'totalStudents' => $students->count(),
                'totalApplications' => $applications->count(),
                'pendingReviews' => $applications->whereIn('application_status', ['submitted', 'under_review'])->count(),
                'approvedApplications' => $applications->where('application_status', 'approved')->count(),
                'rejectedApplications' => $applications->where('application_status', 'rejected')->count(),
                'underReviewCount' => $applications->where('application_status', 'under_review')->count(),
            ],
            'reviewerWorkload' => $applications
                ->groupBy('assigned_reviewer_user_id')
                ->map(static fn (Collection $items, ?string $reviewerId): array => [
                    'reviewerUserId' => $reviewerId,
                    'count' => $items->count(),
                ])
                ->values()
                ->all(),
            'recentSubmissions' => $applications
                ->sortByDesc('submission_date')
                ->take(5)
                ->values()
                ->all(),
            'recentDecisions' => $applications
                ->filter(static fn (ScholarshipApplication $application): bool => in_array($application->application_status, ['approved', 'rejected'], true))
                ->sortByDesc(fn (ScholarshipApplication $application): mixed => $application->approved_at ?? $application->rejected_at ?? $application->updated_at)
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    public function generateStudentCode(): string
    {
        $maxNumeric = (int) ScholarshipStudent::withTrashed()
            ->pluck('student_code')
            ->map(static function (?string $code): int {
                if (! is_string($code)) {
                    return 0;
                }

                return (int) preg_replace('/^SCH-STU-/', '', $code);
            })
            ->max();

        return 'SCH-STU-'.str_pad((string) ($maxNumeric + 1), 3, '0', STR_PAD_LEFT);
    }

    public function generateApplicationCode(): string
    {
        $maxNumeric = (int) ScholarshipApplication::withTrashed()
            ->pluck('application_code')
            ->map(static function (?string $code): int {
                if (! is_string($code)) {
                    return 0;
                }

                return (int) preg_replace('/^SCH-APP-/', '', $code);
            })
            ->max();

        return 'SCH-APP-'.str_pad((string) ($maxNumeric + 1), 3, '0', STR_PAD_LEFT);
    }

    public function applyStatusTransition(ScholarshipApplication $application, string $newStatus, User $actor, ?string $note = null, ?string $rejectionReason = null, ?int $assignedReviewerId = null): ScholarshipApplication
    {
        $previousStatus = $application->application_status;

        $application->application_status = $newStatus;

        if ($assignedReviewerId !== null) {
            $application->assigned_reviewer_user_id = (string) $assignedReviewerId;
        }

        if (in_array($newStatus, ['submitted', 'under_review', 'approved', 'rejected'], true)) {
            $application->reviewed_at = now();
        }

        if ($newStatus === 'approved') {
            $application->approved_at = now();
            $application->rejected_at = null;
            $application->rejection_reason = null;
        } elseif ($newStatus === 'rejected') {
            $application->rejected_at = now();
            $application->approved_at = null;
            $application->rejection_reason = $rejectionReason;
        }

        if ($note !== null) {
            $application->notes = $application->notes ? trim($application->notes."\n".$note) : $note;
        }

        $application->save();

        ScholarshipStatusHistory::query()->create([
            'application_id' => $application->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $actor->id,
            'note' => $note,
            'created_at' => now(),
        ]);

        return $application->refresh()->load(['student', 'assignedReviewer', 'reviews.reviewer', 'statusHistories.changedBy']);
    }

    public function teacherApplicationScope(User $user): \Closure
    {
        return static function (Builder $query) use ($user): Builder {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder->where('assigned_reviewer_user_id', $user->id)
                    ->orWhereHas('reviews', static function (Builder $reviewQuery) use ($user): void {
                        $reviewQuery->where('reviewer_user_id', $user->id);
                    });
            });
        };
    }
}
