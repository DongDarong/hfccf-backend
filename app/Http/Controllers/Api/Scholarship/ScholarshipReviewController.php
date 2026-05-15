<?php

namespace App\Http\Controllers\Api\Scholarship;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarship\StoreScholarshipReviewRequest;
use App\Http\Requests\Scholarship\UpdateScholarshipReviewRequest;
use App\Http\Resources\Scholarship\ScholarshipReviewResource;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipReview;
use App\Models\User;
use App\Services\ScholarshipService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScholarshipReviewController extends Controller
{
    public function __construct(
        private readonly ScholarshipService $scholarshipService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recommendation' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $recommendation = trim((string) ($validated['recommendation'] ?? ''));

        $query = ScholarshipReview::query()->with(['application.student', 'reviewer']);

        if ($search !== '') {
            $query->whereHas('application', static function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('application_code', 'like', $like)
                    ->orWhereHas('student', static function (Builder $studentQuery) use ($like): void {
                        $studentQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        if ($recommendation !== '') {
            $query->where('recommendation', $recommendation);
        }

        $paginator = $query
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Scholarship reviews retrieved successfully.',
            $paginator,
            $request,
            ScholarshipReviewResource::class,
        );
    }

    public function store(StoreScholarshipReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($response = $this->authorizeScholarshipReviewer($user)) {
            return $response;
        }

        $data = $request->validated();
        $application = $this->findApplicationForReviewer($user, (int) $data['application_id']);
        if (! $application) {
            return ApiResponse::errorResponse('Scholarship application not found.', null, Response::HTTP_NOT_FOUND);
        }

        $review = ScholarshipReview::query()->create([
            'application_id' => $application->id,
            'reviewer_user_id' => $user->id,
            'score' => $data['score'] ?? null,
            'recommendation' => $data['recommendation'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_at' => $data['reviewed_at'] ?? now(),
        ]);

        if ($application->application_status === 'draft') {
            $this->scholarshipService->applyStatusTransition($application, 'under_review', $user, $data['review_note'] ?? null, null, (int) $user->id);
        } else {
            $application->forceFill([
                'reviewed_at' => $application->reviewed_at ?? now(),
                'assigned_reviewer_user_id' => $application->assigned_reviewer_user_id ?? $user->id,
            ])->save();
        }

        $review->load(['application.student', 'reviewer']);

        return ApiResponse::successResponse(
            'Scholarship review created successfully.',
            [
                'review' => ScholarshipReviewResource::make($review)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateScholarshipReviewRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($response = $this->authorizeScholarshipReviewer($user)) {
            return $response;
        }

        $review = ScholarshipReview::query()->with(['application.student', 'reviewer'])->find($id);
        if (! $review) {
            return ApiResponse::errorResponse('Scholarship review not found.', null, Response::HTTP_NOT_FOUND);
        }

        if (! in_array($user->role_code, ['superadmin', 'adminscholarship'], true) && $review->reviewer_user_id !== $user->id) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();
        foreach (['application_id', 'score', 'recommendation', 'review_note', 'reviewed_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $review->{$field} = $data[$field];
            }
        }

        $review->save();
        $review->load(['application.student', 'reviewer']);

        return ApiResponse::successResponse(
            'Scholarship review updated successfully.',
            [
                'review' => ScholarshipReviewResource::make($review)->resolve($request),
            ],
        );
    }

    private function authorizeScholarshipAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminscholarship'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    private function authorizeScholarshipReviewer(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminscholarship', 'teacher-scholarship'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    private function findApplicationForReviewer(User $user, int $applicationId): ?ScholarshipApplication
    {
        $query = ScholarshipApplication::query()->where('id', $applicationId);

        if ($user->role_code === 'teacher-scholarship') {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('assigned_reviewer_user_id', $user->id)
                    ->orWhereHas('reviews', static function (Builder $reviewQuery) use ($user): void {
                        $reviewQuery->where('reviewer_user_id', $user->id);
                    });
            });
        }

        return $query->first();
    }

}
