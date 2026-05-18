<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreCoachTeamMatchRequest;
use App\Http\Requests\Sport\StoreCoachTeamPlayerRequest;
use App\Http\Resources\Sport\SportMatchResource;
use App\Http\Resources\Sport\SportPlayerResource;
use App\Http\Resources\Sport\SportTeamResource;
use App\Models\SportTeam;
use App\Support\ApiResponse;
use App\Support\SportCoachAssignmentService;
use App\Support\SportCoachRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportCoachTeamController extends SportController
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportCoachRequestService $requestService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $coach = $request->user();

        if (! $coach || ! in_array($coach->role_code, ['superadmin', 'adminsport', 'coach'], true)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $teams = in_array($coach->role_code, ['superadmin', 'adminsport'], true)
            ? SportTeam::query()->with(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy'])->orderBy('name')->get()
            : $this->assignmentService->assignedTeamsForCoach($coach)->loadMissing(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy']);

        return ApiResponse::successResponse('Coach teams retrieved successfully.', [
            'items' => SportTeamResource::collection($teams)->resolve($request),
            'pagination' => [
                'page' => 1,
                'perPage' => $teams->count() ?: 10,
                'total' => $teams->count(),
                'totalPages' => 1,
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $coach = $request->user();

        if (! $coach || ! in_array($coach->role_code, ['superadmin', 'adminsport', 'coach'], true)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $team = SportTeam::query()
            ->with(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy', 'players.team', 'players.createdBy', 'players.approvedBy', 'players.memberships'])
            ->find($id);

        if (! $team) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        if (! in_array($coach->role_code, ['superadmin', 'adminsport'], true) && ! $this->assignmentService->coachCanManageTeam($coach, $team)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        return ApiResponse::successResponse('Coach team retrieved successfully.', [
            'team' => SportTeamResource::make($team)->resolve($request),
            'players' => SportPlayerResource::collection($team->players)->resolve($request),
        ]);
    }

    public function storePlayer(StoreCoachTeamPlayerRequest $request, string $teamId): JsonResponse
    {
        $coach = $request->user();
        $team = $this->resolveTeamReference($teamId);

        if (! $coach || ! $team) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        if (! in_array($coach->role_code, ['superadmin', 'adminsport'], true) && ! $this->assignmentService->coachCanManageTeam($coach, $team)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();

        try {
            $player = $this->requestService->createPendingPlayerRequest($coach, $team, $data, $request->file('photo'));
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse(
            'Coach player request created successfully.',
            [
                'player' => SportPlayerResource::make($player->loadMissing(['team', 'createdBy']))->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function storeMatch(StoreCoachTeamMatchRequest $request): JsonResponse
    {
        $coach = $request->user();

        if (! $coach) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->validated();
        $coachTeam = $this->resolveTeamReference((string) $data['team_id']);
        $opponentTeam = $this->resolveTeamReference((string) $data['opponent_team_id']);

        if (! $coachTeam || ! $opponentTeam) {
            return ApiResponse::errorResponse('One or both teams could not be found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($coachTeam->id === $opponentTeam->id) {
            return ApiResponse::errorResponse('Teams must be different.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! in_array($coach->role_code, ['superadmin', 'adminsport'], true) && ! $this->assignmentService->coachCanManageTeam($coach, $coachTeam)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $match = $this->requestService->createPendingMatchRequest($coach, $coachTeam, $opponentTeam, $data);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse(
            'Coach match request created successfully.',
            [
                'match' => SportMatchResource::make($match->loadMissing(['homeTeam', 'awayTeam']))->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }
}
