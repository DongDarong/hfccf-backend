<?php

namespace App\Support;

use App\Http\Resources\Preschool\PreschoolAssessmentCategoryResource;
use App\Http\Resources\Preschool\PreschoolStudentAssessmentResource;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolAssessmentService
{
    /**
     * Centralize access and lifecycle checks so Preschool assessments remain
     * draft-editable for staff while finalized records stay immutable.
     */
    public function listCategories(): Collection
    {
        return PreschoolAssessmentCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listAssessments(User $user, PreschoolStudent $student, array $filters = []): LengthAwarePaginator
    {
        $this->ensureUserCanAccessStudent($user, $student, $this->nullableInt($filters['class_id'] ?? null));

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $status = trim((string) ($filters['status'] ?? ''));
        $categoryId = $this->nullableInt($filters['category_id'] ?? null);
        $periodLabel = trim((string) ($filters['period_label'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $sortBy = (string) ($filters['sort_by'] ?? 'assessment_date');
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = PreschoolStudentAssessment::query()
            ->with(['student', 'preschoolClass', 'category', 'assessedBy', 'finalizedBy'])
            ->where('student_id', $student->id);

        if ($status !== '' && in_array($status, PreschoolAssessmentStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($periodLabel !== '') {
            $query->where('period_label', 'like', '%'.$periodLabel.'%');
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('period_label', 'like', $like)
                    ->orWhere('observation', 'like', $like)
                    ->orWhere('teacher_comment', 'like', $like)
                    ->orWhereHas('category', static function (Builder $categoryQuery) use ($like): void {
                        $categoryQuery->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        $sortColumn = match ($sortBy) {
            'assessment_date' => 'assessment_date',
            'period_label' => 'period_label',
            'status' => 'status',
            'score' => 'score',
            default => 'assessment_date',
        };

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createAssessment(User $user, PreschoolStudent $student, array $data): PreschoolStudentAssessment
    {
        $classId = $this->resolveClassIdForUser($user, $student, $this->nullableInt($data['class_id'] ?? null));

        $assessment = new PreschoolStudentAssessment([
            'student_id' => $student->id,
            'class_id' => $classId,
            'category_id' => (int) $data['category_id'],
            'assessed_by_user_id' => $user->id,
            'period_label' => trim((string) $data['period_label']),
            'assessment_date' => $data['assessment_date'],
            'score' => $data['score'] ?? null,
            'rating' => $data['rating'] ?? null,
            'observation' => $data['observation'] ?? null,
            'teacher_comment' => $data['teacher_comment'] ?? null,
            'status' => PreschoolAssessmentStatus::DRAFT,
        ]);

        $assessment->save();
        $assessment->load(['student', 'preschoolClass', 'category', 'assessedBy', 'finalizedBy']);

        return $assessment;
    }

    public function updateAssessment(User $user, PreschoolStudentAssessment $assessment, array $data): PreschoolStudentAssessment
    {
        $this->ensureUserCanManageAssessment($user, $assessment);

        if ($assessment->status !== PreschoolAssessmentStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Finalized or archived assessments cannot be edited.',
            ]);
        }

        if (array_key_exists('class_id', $data)) {
            $assessment->class_id = $this->resolveClassIdForUser(
                $user,
                $assessment->student()->firstOrFail(),
                $this->nullableInt($data['class_id'] ?? null),
            );
        }

        foreach (['category_id', 'period_label', 'assessment_date', 'score', 'rating', 'observation', 'teacher_comment'] as $field) {
            if (array_key_exists($field, $data)) {
                $assessment->{$field} = $data[$field];
            }
        }

        $assessment->save();
        $assessment->load(['student', 'preschoolClass', 'category', 'assessedBy', 'finalizedBy']);

        return $assessment;
    }

    public function finalizeAssessment(User $user, PreschoolStudentAssessment $assessment): PreschoolStudentAssessment
    {
        $this->ensureUserCanManageAssessment($user, $assessment);

        if ($assessment->status === PreschoolAssessmentStatus::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'Archived assessments cannot be finalized.',
            ]);
        }

        $assessment->status = PreschoolAssessmentStatus::FINALIZED;
        $assessment->finalized_at = Carbon::now();
        $assessment->finalized_by_user_id = $user->id;
        $assessment->save();
        $assessment->load(['student', 'preschoolClass', 'category', 'assessedBy', 'finalizedBy']);

        return $assessment;
    }

    public function archiveAssessment(User $user, PreschoolStudentAssessment $assessment): PreschoolStudentAssessment
    {
        $this->ensureUserCanManageAssessment($user, $assessment);

        if ($assessment->status === PreschoolAssessmentStatus::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'Assessment is already archived.',
            ]);
        }

        $assessment->status = PreschoolAssessmentStatus::ARCHIVED;
        $assessment->save();
        $assessment->load(['student', 'preschoolClass', 'category', 'assessedBy', 'finalizedBy']);

        return $assessment;
    }

    public function progressSummary(User $user, PreschoolStudent $student): array
    {
        $this->ensureUserCanAccessStudent($user, $student);

        $assessments = PreschoolStudentAssessment::query()
            ->with(['student', 'preschoolClass', 'category', 'assessedBy', 'finalizedBy'])
            ->where('student_id', $student->id)
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get();

        $finalized = $assessments->where('status', PreschoolAssessmentStatus::FINALIZED);
        $categories = $this->listCategories()->map(function (PreschoolAssessmentCategory $category) use ($finalized): array {
            $categoryAssessments = $finalized->where('category_id', $category->id);
            $scores = $categoryAssessments->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);

            return [
                'category' => PreschoolAssessmentCategoryResource::make($category)->resolve(),
                'count' => $categoryAssessments->count(),
                'averageScore' => $scores->count() ? round($scores->avg(), 2) : null,
                'latestAssessmentDate' => $categoryAssessments->first()?->assessment_date?->toDateString(),
            ];
        })->values();

        $scores = $finalized->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);

        return [
            'summary' => [
                'totalAssessments' => $assessments->count(),
                'draftAssessments' => $assessments->where('status', PreschoolAssessmentStatus::DRAFT)->count(),
                'finalizedAssessments' => $finalized->count(),
                'archivedAssessments' => $assessments->where('status', PreschoolAssessmentStatus::ARCHIVED)->count(),
                'averageScore' => $scores->count() ? round($scores->avg(), 2) : null,
                'latestAssessmentDate' => $assessments->first()?->assessment_date?->toDateString(),
            ],
            'categories' => $categories,
            'recentAssessments' => PreschoolStudentAssessmentResource::collection($finalized->take(5))->resolve(),
        ];
    }

    public function ensureUserCanAccessStudent(?User $user, PreschoolStudent $student, ?int $classId = null): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless($user->role_code === 'teacher-preschool', Response::HTTP_FORBIDDEN, 'Forbidden.');

        $accessibleClassIds = PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->pluck('id')
            ->all();

        abort_if($accessibleClassIds === [], Response::HTTP_FORBIDDEN, 'Forbidden.');

        $studentClasses = $student->classes()
            ->whereIn('preschool_classes.id', $accessibleClassIds)
            ->wherePivot('status', 'active')
            ->pluck('preschool_classes.id')
            ->all();

        if ($classId !== null) {
            abort_if(! in_array($classId, $accessibleClassIds, true), Response::HTTP_FORBIDDEN, 'Forbidden.');
            abort_if(! $student->classes()
                ->where('preschool_classes.id', $classId)
                ->wherePivot('status', 'active')
                ->exists(), Response::HTTP_FORBIDDEN, 'Forbidden.');

            return;
        }

        abort_if($studentClasses === [], Response::HTTP_FORBIDDEN, 'Forbidden.');
    }

    public function ensureUserCanManageAssessment(?User $user, PreschoolStudentAssessment $assessment): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless($user->role_code === 'teacher-preschool', Response::HTTP_FORBIDDEN, 'Forbidden.');

        $student = $assessment->student()->firstOrFail();
        $classId = $this->nullableInt($assessment->class_id);

        $this->ensureUserCanAccessStudent($user, $student, $classId);
    }

    private function resolveClassIdForUser(User $user, PreschoolStudent $student, ?int $classId): ?int
    {
        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            if ($classId === null) {
                return null;
            }

            $belongsToClass = $student->classes()
                ->where('preschool_classes.id', $classId)
                ->wherePivot('status', 'active')
                ->exists();

            abort_unless($belongsToClass, Response::HTTP_FORBIDDEN, 'Forbidden.');

            return $classId;
        }

        $accessibleClassIds = PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->pluck('id')
            ->all();

        abort_if($accessibleClassIds === [], Response::HTTP_FORBIDDEN, 'Forbidden.');

        if ($classId !== null) {
            abort_if(! in_array($classId, $accessibleClassIds, true), Response::HTTP_FORBIDDEN, 'Forbidden.');
            abort_if(! $student->classes()
                ->where('preschool_classes.id', $classId)
                ->wherePivot('status', 'active')
                ->exists(), Response::HTTP_FORBIDDEN, 'Forbidden.');

            return $classId;
        }

        $fallbackClassId = $student->classes()
            ->whereIn('preschool_classes.id', $accessibleClassIds)
            ->wherePivot('status', 'active')
            ->value('preschool_classes.id');

        abort_if($fallbackClassId === null, Response::HTTP_FORBIDDEN, 'Forbidden.');

        return (int) $fallbackClassId;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
