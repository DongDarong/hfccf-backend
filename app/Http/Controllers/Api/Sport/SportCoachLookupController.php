<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportMatchResource;
use App\Http\Resources\Sport\SportPlayerResource;
use App\Models\SportTeam;
use App\Models\SportPlayer;
use App\Support\ApiResponse;
use App\Support\SportCoachAssignmentService;
use App\Support\SportCoachRequestService;
use App\Support\SportTeamRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportCoachLookupController extends SportController
{
    public function __construct(
        private readonly SportCoachRequestService $requestService,
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportTeamRosterService $rosterService,
    ) {}

    public function requests(Request $request): JsonResponse
    {
        $coach = $request->user();

        if (! $coach || $coach->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $requests = $this->requestService->coachRequests($coach);

        return ApiResponse::successResponse('Coach requests retrieved successfully.', [
            'playerRequests' => SportPlayerResource::collection($requests['playerRequests'])->resolve($request),
            'matchRequests' => SportMatchResource::collection($requests['matchRequests'])->resolve($request),
            'summary' => [
                'playerRequests' => $requests['playerRequests']->count(),
                'matchRequests' => $requests['matchRequests']->count(),
                'total' => $requests['playerRequests']->count() + $requests['matchRequests']->count(),
            ],
        ]);
    }

    public function opponentTeams(Request $request): JsonResponse
    {
        $coach = $request->user();

        if (! $coach || $coach->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $teams = $this->assignmentService->opponentTeamsForCoach($coach);

        return ApiResponse::successResponse('Coach opponent teams retrieved successfully.', [
            'items' => $teams->map(static fn (SportTeam $team): array => [
                'id' => $team->id,
                'teamCode' => $team->team_code,
                'name' => $team->name,
                'shortName' => $team->short_name,
                'division' => $team->division,
                'status' => $team->status,
            ])->values()->all(),
            'pagination' => [
                'page' => 1,
                'perPage' => $teams->count() ?: 10,
                'total' => $teams->count(),
                'totalPages' => 1,
            ],
        ]);
    }

    public function rosterCandidates(Request $request, string $team): JsonResponse
    {
        $coach = $request->user();
        $teamModel = SportTeam::query()->with(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy', 'coachAssignments.coach', 'coachAssignments.assignedBy'])->find($team);

        if (! $coach || $coach->role_code !== 'coach' || ! $teamModel) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        if (! $this->assignmentService->coachCanManageTeam($coach, $teamModel)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $players = $this->rosterService->eligiblePlayersForTeam($teamModel);

        return ApiResponse::successResponse('Coach roster candidates retrieved successfully.', [
            'team' => [
                'id' => $teamModel->id,
                'teamCode' => $teamModel->team_code,
                'name' => $teamModel->name,
                'shortName' => $teamModel->short_name,
                'division' => $teamModel->division,
            ],
            'items' => $players->map(static fn (SportPlayer $player): array => [
                'id' => $player->id,
                'playerCode' => $player->player_code,
                'firstName' => $player->first_name,
                'lastName' => $player->last_name,
                'name' => trim($player->first_name.' '.$player->last_name),
                'jerseyNumber' => $player->jersey_number,
                'position' => $player->position,
                'division' => $player->division,
                'approvalStatus' => $player->approval_status,
                'rosterStatus' => $player->roster_status ?? $player->status,
                'teamId' => $player->team_id,
                'teamName' => $player->team?->name,
            ])->values()->all(),
            'pagination' => [
                'page' => 1,
                'perPage' => $players->count() ?: 10,
                'total' => $players->count(),
                'totalPages' => 1,
            ],
        ]);
    }
}
