<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreTournamentMatchEventRequest;
use App\Http\Requests\Sport\StoreTournamentMatchResultRequest;
use App\Http\Resources\Sport\SportTournamentMatchEventResource;
use App\Http\Resources\Sport\SportTournamentMatchResource;
use App\Http\Resources\Sport\SportTournamentStandingResource;
use App\Models\SportMatch;
use App\Models\SportTournament;
use App\Support\ApiResponse;
use App\Support\SportTournament\TournamentResultService;
use App\Support\SportTournament\TournamentStandingsService;
use App\Support\SportTournament\TournamentStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTournamentResultController extends SportController
{
    public function __construct(
        private readonly TournamentResultService $resultService,
        private readonly TournamentStandingsService $standingsService,
        private readonly TournamentStatisticsService $statisticsService,
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

        $matches = $tournament->matches()
            ->with(['homeTeam', 'awayTeam', 'group', 'knockoutRound', 'events.team', 'events.player', 'events.assistPlayer', 'events.playerIn', 'events.playerOut'])
            ->withCount('events')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $stats = $this->statisticsService->calculate($tournament);
        $standings = $this->standingsService->calculate($tournament);

        return ApiResponse::successResponse('Tournament results retrieved successfully.', [
            'tournamentId' => $tournament->id,
            'matches' => SportTournamentMatchResource::collection($matches)->resolve($request),
            'summary' => $stats['summary'] ?? [],
            'standings' => SportTournamentStandingResource::collection(collect($standings['overall']))->resolve($request),
        ]);
    }

    public function show(Request $request, string $id, string $matchId): JsonResponse
    {
        if ($response = $this->authorizeSportCoach($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);
        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $match = SportMatch::query()->with(['homeTeam', 'awayTeam', 'group', 'knockoutRound', 'events.team', 'events.player', 'events.assistPlayer', 'events.playerIn', 'events.playerOut'])->withCount('events')->where('tournament_id', $tournament->id)->find($matchId);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse('Tournament match retrieved successfully.', [
            'match' => SportTournamentMatchResource::make($match)->resolve($request),
            'events' => SportTournamentMatchEventResource::collection($match->events)->resolve($request),
        ]);
    }

    public function update(StoreTournamentMatchResultRequest $request, string $id, string $matchId): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);
        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $match = SportMatch::query()->where('tournament_id', $tournament->id)->with(['homeTeam', 'awayTeam', 'group', 'knockoutRound', 'events.team', 'events.player', 'events.assistPlayer', 'events.playerIn', 'events.playerOut'])->withCount('events')->find($matchId);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        try {
            $updated = $this->resultService->saveResult($match, $request->validated(), $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $standings = $this->standingsService->calculate($tournament);
        $statistics = $this->statisticsService->calculate($tournament);

        $standingsRows = $standings['overall'] ?? [];

        if ($match->group_id) {
            foreach ($standings['groups'] ?? [] as $groupSummary) {
                if ((int) ($groupSummary['group']->id ?? 0) === (int) $match->group_id) {
                    $standingsRows = $groupSummary['standings'] ?? $standingsRows;
                    break;
                }
            }
        }

        return ApiResponse::successResponse('Tournament match result saved successfully.', [
            'match' => SportTournamentMatchResource::make($updated)->resolve($request),
            'standings' => SportTournamentStandingResource::collection(collect($standingsRows))->resolve($request),
            'summary' => $statistics['summary'] ?? [],
        ]);
    }

    public function storeEvent(StoreTournamentMatchEventRequest $request, string $id, string $matchId): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);
        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $match = SportMatch::query()->where('tournament_id', $tournament->id)->with(['homeTeam', 'awayTeam', 'group', 'knockoutRound', 'events.team', 'events.player', 'events.assistPlayer', 'events.playerIn', 'events.playerOut'])->withCount('events')->find($matchId);
        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        $teamId = (int) $request->validated()['team_id'];
        if (! in_array($teamId, [(int) $match->home_team_id, (int) $match->away_team_id], true)) {
            return ApiResponse::errorResponse('Event team must belong to this match.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $event = $this->resultService->saveEvent($match, $request->validated(), $request->user());
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $match->refresh()->load(['homeTeam', 'awayTeam', 'group', 'knockoutRound', 'events.team', 'events.player', 'events.assistPlayer', 'events.playerIn', 'events.playerOut'])->loadCount('events');

        return ApiResponse::successResponse('Tournament match event saved successfully.', [
            'event' => SportTournamentMatchEventResource::make($event)->resolve($request),
            'match' => SportTournamentMatchResource::make($match)->resolve($request),
        ], Response::HTTP_CREATED);
    }
}
