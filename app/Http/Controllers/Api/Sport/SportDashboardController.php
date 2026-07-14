<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportMatchEventResource;
use App\Http\Resources\Sport\SportMatchResource;
use App\Http\Resources\Sport\SportPlayerResource;
use App\Http\Resources\Sport\SportStandingResource;
use App\Http\Resources\Sport\SportTeamResource;
use App\Http\Resources\Sport\SportTournamentResource;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportPlayer;
use App\Models\SportStanding;
use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\SportEquipmentService;
use App\Support\SportCoachAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportDashboardController extends SportController
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportEquipmentService $equipmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        return ApiResponse::successResponse('Sport dashboard retrieved successfully.', $this->buildDashboardPayload($request));
    }

    public function coach(Request $request): JsonResponse
    {
        if (! $request->user() || $request->user()->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $coach = $request->user();
        $teams = $this->assignmentService->assignedTeamsForCoach($coach)->loadMissing(['coach', 'activeCoachAssignment.coach', 'activeCoachAssignment.assignedBy']);

        $teamIds = $this->assignmentService->assignedTeamsForCoach($coach)->pluck('id')->all();

        $matches = SportMatch::query()
            ->with(['homeTeam', 'awayTeam', 'events'])
            ->withCount('events')
            ->where(function ($query) use ($teamIds): void {
                $query->whereIn('home_team_id', $teamIds)
                    ->orWhereIn('away_team_id', $teamIds);
            })
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return ApiResponse::successResponse('Coach dashboard retrieved successfully.', [
            'summary' => [
                'teams' => $teams->count(),
                'matches' => $matches->count(),
                'liveMatches' => $matches->where('status', 'live')->count(),
                'completedMatches' => $matches->where('status', 'completed')->count(),
            ],
            'teams' => SportTeamResource::collection($teams)->resolve($request),
            'matches' => SportMatchResource::collection($matches)->resolve($request),
        ]);
    }

    private function buildDashboardPayload(Request $request): array
    {
        $teams = SportTeam::query()->with(['coach'])->orderBy('name')->get();
        $players = SportPlayer::query()->with(['team'])->orderBy('first_name')->orderBy('last_name')->get();
        $recentMatches = SportMatch::query()->with(['homeTeam', 'awayTeam', 'tournament', 'events'])->withCount('events')->orderByDesc('scheduled_at')->orderByDesc('id')->limit(10)->get();
        $recentEvents = SportMatchEvent::query()->with(['match.homeTeam', 'match.awayTeam', 'team', 'player'])->orderByDesc('id')->limit(10)->get();
        $tournamentCount = SportTournament::query()->count();
        $activeTournamentCount = SportTournament::query()->where('status', 'active')->count();
        $tournaments = SportTournament::query()
            ->withCount(['teams', 'matches', 'standings'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'draft' THEN 1 WHEN status = 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();
        $featuredTournament = $tournaments->firstWhere('status', 'active') ?? $tournaments->first();
        $featuredStandings = $featuredTournament
            ? SportStanding::query()->with(['team'])->where('tournament_id', $featuredTournament->id)->orderBy('rank_position')->orderBy('id')->limit(10)->get()
            : collect();

        return [
            'summary' => [
                'teams' => $teams->count(),
                'players' => $players->count(),
                'matches' => SportMatch::query()->count(),
                'scheduledMatches' => SportMatch::query()->where('status', 'scheduled')->count(),
                'liveMatches' => SportMatch::query()->where('status', 'live')->count(),
                'completedMatches' => SportMatch::query()->where('status', 'completed')->count(),
                'coaches' => User::query()->where('role_code', 'coach')->count(),
                'tournaments' => $tournamentCount,
                'activeTournaments' => $activeTournamentCount,
                ...$this->equipmentService->summary(),
            ],
            'teams' => SportTeamResource::collection($teams)->resolve($request),
            'players' => SportPlayerResource::collection($players)->resolve($request),
            'matches' => SportMatchResource::collection($recentMatches)->resolve($request),
            'events' => SportMatchEventResource::collection($recentEvents)->resolve($request),
            'tournaments' => SportTournamentResource::collection($tournaments)->resolve($request),
            'featuredTournament' => $featuredTournament ? SportTournamentResource::make($featuredTournament)->resolve($request) : null,
            'standings' => SportStandingResource::collection($featuredStandings)->resolve($request),
        ];
    }
}
