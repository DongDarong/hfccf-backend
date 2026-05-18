<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreCoachTeamAssignmentRequest;
use App\Http\Requests\Sport\UpdateCoachTeamAssignmentRequest;
use App\Http\Resources\Sport\SportCoachTeamAssignmentResource;
use App\Models\CoachTeamAssignment;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\SportCoachAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportAdminCoachTeamAssignmentController extends SportController
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:16'],
            'coach_user_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $coachUserId = trim((string) ($validated['coach_user_id'] ?? ''));
        $teamId = (int) ($validated['team_id'] ?? 0);

        $query = CoachTeamAssignment::query()->with(['coach', 'team', 'assignedBy']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->whereHas('coach', static function (Builder $coachQuery) use ($like): void {
                    $coachQuery->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })->orWhereHas('team', static function (Builder $teamQuery) use ($like): void {
                    $teamQuery->where('team_code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('short_name', 'like', $like);
                });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($coachUserId !== '') {
            $query->where('coach_user_id', $coachUserId);
        }

        if ($teamId > 0) {
            $query->where('team_id', $teamId);
        }

        $paginator = $query
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Coach team assignments retrieved successfully.',
            $paginator,
            $request,
            SportCoachTeamAssignmentResource::class,
        );
    }

    public function store(StoreCoachTeamAssignmentRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $coach = User::query()->where('role_code', 'coach')->find($data['coach_user_id']);
        $team = SportTeam::query()->find($data['team_id']);

        if (! $coach || ! $team) {
            return ApiResponse::errorResponse('Coach or team not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $assignment = $this->assignmentService->assignTeamToCoach($team, $coach, $request->user());

        return ApiResponse::successResponse(
            'Coach team assignment created successfully.',
            [
                'assignment' => SportCoachTeamAssignmentResource::make($assignment)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateCoachTeamAssignmentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $assignment = CoachTeamAssignment::query()->with(['coach', 'team', 'assignedBy'])->find($id);

        if (! $assignment) {
            return ApiResponse::errorResponse('Coach team assignment not found.', null, Response::HTTP_NOT_FOUND);
        }

        $assignment = $this->assignmentService->updateAssignment($assignment, $request->validated(), $request->user());

        return ApiResponse::successResponse(
            'Coach team assignment updated successfully.',
            [
                'assignment' => SportCoachTeamAssignmentResource::make($assignment)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $assignment = CoachTeamAssignment::query()->with(['coach', 'team', 'assignedBy'])->find($id);

        if (! $assignment) {
            return ApiResponse::errorResponse('Coach team assignment not found.', null, Response::HTTP_NOT_FOUND);
        }

        $assignment = $this->assignmentService->deactivateAssignment($assignment, $request->user());

        return ApiResponse::successResponse(
            'Coach team assignment deactivated successfully.',
            [
                'assignment' => SportCoachTeamAssignmentResource::make($assignment)->resolve($request),
            ],
        );
    }
}
