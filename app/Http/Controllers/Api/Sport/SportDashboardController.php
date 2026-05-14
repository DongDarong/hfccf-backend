<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportMatchEventResource;
use App\Http\Resources\Sport\SportMatchResource;
use App\Http\Resources\Sport\SportPlayerResource;
use App\Http\Resources\Sport\SportTeamResource;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportDashboardController extends SportController
{
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
        $teams = SportTeam::query()->with(['coach'])->where('coach_user_id', $coach->id)->orderBy('name')->get();

        $matches = SportMatch::query()
            ->with(['homeTeam', 'awayTeam', 'events'])
            ->withCount('events')
            ->where(function ($query) use ($coach): void {
                $query->whereHas('homeTeam', fn ($builder) => $builder->where('coach_user_id', $coach->id))
                    ->orWhereHas('awayTeam', fn ($builder) => $builder->where('coach_user_id', $coach->id));
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
        $recentMatches = SportMatch::query()->with(['homeTeam', 'awayTeam', 'events'])->withCount('events')->orderByDesc('scheduled_at')->orderByDesc('id')->limit(10)->get();
        $recentEvents = SportMatchEvent::query()->with(['match.homeTeam', 'match.awayTeam', 'team', 'player'])->orderByDesc('id')->limit(10)->get();

        return [
            'summary' => [
                'teams' => $teams->count(),
                'players' => $players->count(),
                'matches' => SportMatch::query()->count(),
                'scheduledMatches' => SportMatch::query()->where('status', 'scheduled')->count(),
                'liveMatches' => SportMatch::query()->where('status', 'live')->count(),
                'completedMatches' => SportMatch::query()->where('status', 'completed')->count(),
                'coaches' => User::query()->where('role_code', 'coach')->count(),
            ],
            'teams' => SportTeamResource::collection($teams)->resolve($request),
            'players' => SportPlayerResource::collection($players)->resolve($request),
            'matches' => SportMatchResource::collection($recentMatches)->resolve($request),
            'events' => SportMatchEventResource::collection($recentEvents)->resolve($request),
        ];
    }
}
