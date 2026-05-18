<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\DrawTournamentGroupsRequest;
use App\Http\Resources\Sport\SportTournamentGroupResource;
use App\Http\Resources\Sport\SportTournamentStandingResource;
use App\Models\SportTournament;
use App\Support\ApiResponse;
use App\Support\SportTournament\TournamentGroupService;
use App\Support\SportTournament\TournamentStandingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTournamentGroupController extends SportController
{
    public function __construct(
        private readonly TournamentGroupService $groupService,
        private readonly TournamentStandingsService $standingsService,
    ) {
    }

    public function index(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportCoach($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($this->isSportCoach($request->user()) && ! $this->canAccessTournament($request->user(), $tournament)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $groups = $this->groupService->list($tournament);
        $standings = $this->standingsService->calculate($tournament);

        return ApiResponse::successResponse('Tournament groups retrieved successfully.', [
            'tournamentId' => $tournament->id,
            'groups' => SportTournamentGroupResource::collection($groups)->resolve($request),
            'groupStandings' => collect($standings['groups'] ?? [])->map(function (array $groupSummary) use ($request): array {
                return [
                    'group' => [
                        'id' => $groupSummary['group']->id,
                        'name' => $groupSummary['group']->name,
                        'code' => $groupSummary['group']->code,
                        'position' => $groupSummary['group']->position,
                        'qualificationSlots' => $groupSummary['group']->qualification_slots,
                    ],
                    'standings' => SportTournamentStandingResource::collection(collect($groupSummary['standings'] ?? []))->resolve($request),
                ];
            })->values()->all(),
            'standings' => SportTournamentStandingResource::collection(collect($standings['overall']))->resolve($request),
        ]);
    }

    public function draw(DrawTournamentGroupsRequest $request, string $id): JsonResponse
    {
        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $groups = $this->groupService->draw($tournament, $request->validated());

        return ApiResponse::successResponse('Tournament groups drawn successfully.', [
            'tournamentId' => $tournament->id,
            'groups' => SportTournamentGroupResource::collection($groups)->resolve($request),
        ]);
    }

    public function finalize(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $groups = $this->groupService->finalize($tournament);

        return ApiResponse::successResponse('Tournament groups finalized successfully.', [
            'tournamentId' => $tournament->id,
            'groups' => SportTournamentGroupResource::collection($groups)->resolve($request),
        ]);
    }
}
