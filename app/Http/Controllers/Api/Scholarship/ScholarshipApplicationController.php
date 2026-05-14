<?php

namespace App\Http\Controllers\Api\Scholarship;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarship\StoreScholarshipApplicationRequest;
use App\Http\Requests\Scholarship\UpdateScholarshipApplicationRequest;
use App\Http\Requests\Scholarship\UpdateScholarshipStatusRequest;
use App\Http\Resources\Scholarship\ScholarshipApplicationResource;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Services\ScholarshipService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScholarshipApplicationController extends Controller
{
    public function __construct(
        private readonly ScholarshipService $scholarshipService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($response = $this->authorizeScholarshipViewer($user)) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'scholarship_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'academic_year' => ['sometimes', 'nullable', 'string', 'max:20'],
            'student_id' => ['sometimes', 'nullable', 'integer'],
            'assigned_reviewer_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $scholarshipType = trim((string) ($validated['scholarship_type'] ?? ''));
        $academicYear = trim((string) ($validated['academic_year'] ?? ''));
        $studentId = (int) ($validated['student_id'] ?? 0);
        $reviewerId = trim((string) ($validated['assigned_reviewer_user_id'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = $this->buildApplicationQuery($user);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('application_code', 'like', $like)
                    ->orWhere('scholarship_type', 'like', $like)
                    ->orWhere('academic_year', 'like', $like)
                    ->orWhereHas('student', static function (Builder $studentQuery) use ($like): void {
                        $studentQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('student_code', 'like', $like);
                    });
            });
        }

        if ($status !== '') {
            $query->where('application_status', $status);
        }

        if ($scholarshipType !== '') {
            $query->where('scholarship_type', $scholarshipType);
        }

        if ($academicYear !== '') {
            $query->where('academic_year', $academicYear);
        }

        if ($studentId > 0) {
            $query->where('student_id', $studentId);
        }

        if ($reviewerId !== '') {
            $query->where('assigned_reviewer_user_id', $reviewerId);
        }

        $sortColumn = match ($sortBy) {
            'application_code' => 'application_code',
            'scholarship_type' => 'scholarship_type',
            'academic_year' => 'academic_year',
            'application_status' => 'application_status',
            'submission_date' => 'submission_date',
            default => 'created_at',
        };

        $paginator = $query
            ->with(['student', 'assignedReviewer', 'reviews.reviewer', 'statusHistories.changedBy'])
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Scholarship applications retrieved successfully.',
            $paginator,
            $request,
            ScholarshipApplicationResource::class,
        );
    }

    public function reviewerApplications(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function store(StoreScholarshipApplicationRequest $request): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $application = ScholarshipApplication::query()->create([
            'student_id' => $data['student_id'],
            'application_code' => $data['application_code'] ?? $this->generateApplicationCode(),
            'scholarship_type' => $data['scholarship_type'],
            'requested_amount' => $data['requested_amount'],
            'academic_year' => $data['academic_year'],
            'submission_date' => $data['submission_date'],
            'application_status' => $data['application_status'] ?? 'draft',
            'assigned_reviewer_user_id' => $data['assigned_reviewer_user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if (($data['application_status'] ?? 'draft') !== 'draft') {
            $application = $this->scholarshipService->applyStatusTransition(
                $application,
                $data['application_status'],
                $request->user(),
                $data['notes'] ?? null,
                $data['rejection_reason'] ?? null,
                isset($data['assigned_reviewer_user_id']) ? (int) $data['assigned_reviewer_user_id'] : null,
            );
        } else {
            $application->load(['student', 'assignedReviewer', 'reviews.reviewer', 'statusHistories.changedBy']);
        }

        return ApiResponse::successResponse(
            'Scholarship application created successfully.',
            [
                'application' => ScholarshipApplicationResource::make($application)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $application = $this->findApplicationForViewer($request->user(), $id);
        if (! $application) {
            return ApiResponse::errorResponse('Scholarship application not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'Scholarship application retrieved successfully.',
            [
                'application' => ScholarshipApplicationResource::make($application)->resolve($request),
            ],
        );
    }

    public function update(UpdateScholarshipApplicationRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $application = ScholarshipApplication::query()->find($id);
        if (! $application) {
            return ApiResponse::errorResponse('Scholarship application not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        foreach (['student_id', 'application_code', 'scholarship_type', 'requested_amount', 'academic_year', 'submission_date', 'assigned_reviewer_user_id', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $application->{$field} = $data[$field];
            }
        }

        if (array_key_exists('application_status', $data) && $data['application_status'] !== $application->application_status) {
            $application = $this->scholarshipService->applyStatusTransition(
                $application,
                $data['application_status'],
                $request->user(),
                $data['notes'] ?? null,
                $data['rejection_reason'] ?? null,
                isset($data['assigned_reviewer_user_id']) ? (int) $data['assigned_reviewer_user_id'] : null,
            );
        } else {
            $application->save();
            $application->load(['student', 'assignedReviewer', 'reviews.reviewer', 'statusHistories.changedBy']);
        }

        return ApiResponse::successResponse(
            'Scholarship application updated successfully.',
            [
                'application' => ScholarshipApplicationResource::make($application)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $application = ScholarshipApplication::query()->find($id);
        if (! $application) {
            return ApiResponse::errorResponse('Scholarship application not found.', null, Response::HTTP_NOT_FOUND);
        }

        $application->delete();

        return ApiResponse::successResponse('Scholarship application deleted successfully.', null);
    }

    public function approve(UpdateScholarshipStatusRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $application = ScholarshipApplication::query()->find($id);
        if (! $application) {
            return ApiResponse::errorResponse('Scholarship application not found.', null, Response::HTTP_NOT_FOUND);
        }

        $application = $this->scholarshipService->applyStatusTransition(
            $application,
            'approved',
            $request->user(),
            $request->input('note'),
            null,
            $request->filled('assigned_reviewer_user_id') ? (int) $request->input('assigned_reviewer_user_id') : null,
        );

        return ApiResponse::successResponse(
            'Scholarship application approved successfully.',
            [
                'application' => ScholarshipApplicationResource::make($application)->resolve($request),
            ],
        );
    }

    public function reject(UpdateScholarshipStatusRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        if (blank($data['rejection_reason'] ?? null)) {
            return ApiResponse::errorResponse('Rejection reason is required.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application = ScholarshipApplication::query()->find($id);
        if (! $application) {
            return ApiResponse::errorResponse('Scholarship application not found.', null, Response::HTTP_NOT_FOUND);
        }

        $application = $this->scholarshipService->applyStatusTransition(
            $application,
            'rejected',
            $request->user(),
            $data['note'] ?? null,
            $data['rejection_reason'],
            $request->filled('assigned_reviewer_user_id') ? (int) $request->input('assigned_reviewer_user_id') : null,
        );

        return ApiResponse::successResponse(
            'Scholarship application rejected successfully.',
            [
                'application' => ScholarshipApplicationResource::make($application)->resolve($request),
            ],
        );
    }

    public function updateStatus(UpdateScholarshipStatusRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeScholarshipAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $application = ScholarshipApplication::query()->find($id);
        if (! $application) {
            return ApiResponse::errorResponse('Scholarship application not found.', null, Response::HTTP_NOT_FOUND);
        }

        if (($data['application_status'] ?? '') === 'rejected' && blank($data['rejection_reason'] ?? null)) {
            return ApiResponse::errorResponse('Rejection reason is required.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application = $this->scholarshipService->applyStatusTransition(
            $application,
            $data['application_status'],
            $request->user(),
            $data['note'] ?? null,
            $data['rejection_reason'] ?? null,
            $request->filled('assigned_reviewer_user_id') ? (int) $request->input('assigned_reviewer_user_id') : null,
        );

        return ApiResponse::successResponse(
            'Scholarship application status updated successfully.',
            [
                'application' => ScholarshipApplicationResource::make($application)->resolve($request),
            ],
        );
    }

    private function buildApplicationQuery(?User $user): Builder
    {
        $query = ScholarshipApplication::query();

        if ($user?->role_code === 'teacher-scholarship') {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('assigned_reviewer_user_id', $user->id)
                    ->orWhereHas('reviews', static function (Builder $reviewQuery) use ($user): void {
                        $reviewQuery->where('reviewer_user_id', $user->id);
                    });
            });
        }

        return $query;
    }

    private function findApplicationForViewer(?User $user, int $id): ?ScholarshipApplication
    {
        if (! $user) {
            return null;
        }

        $query = ScholarshipApplication::query()->with(['student', 'assignedReviewer', 'reviews.reviewer', 'statusHistories.changedBy']);

        if ($user->role_code === 'teacher-scholarship') {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('assigned_reviewer_user_id', $user->id)
                    ->orWhereHas('reviews', static function (Builder $reviewQuery) use ($user): void {
                        $reviewQuery->where('reviewer_user_id', $user->id);
                    });
            });
        } elseif (! in_array($user->role_code, ['superadmin', 'adminscholarship'], true)) {
            return null;
        }

        return $query->find($id);
    }

    private function authorizeScholarshipViewer(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminscholarship', 'teacher-scholarship'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
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

    private function generateApplicationCode(): string
    {
        return app(\App\Services\ScholarshipService::class)->generateApplicationCode();
    }
}
