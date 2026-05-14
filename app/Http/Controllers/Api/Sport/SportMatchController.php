<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportMatchRequest;
use App\Http\Requests\Sport\UpdateSportMatchRequest;
use App\Http\Requests\Sport\UpdateSportMatchStatusRequest;
use App\Http\Resources\Sport\SportMatchResource;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Support\ApiResponse;
use App\Support\SportMatchScoreService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportMatchController extends SportController
{
    public function __construct(private readonly SportMatchScoreService $scoreService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $teamId = (int) ($validated['team_id'] ?? 0);
        $sortBy = (string) ($validated['sort_by'] ?? 'scheduled_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = SportMatch::query()->with(['homeTeam', 'awayTeam', 'events'])->withCount('events');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('match_code', 'like', $like)
                    ->orWhere('competition_type', 'like', $like)
                    ->orWhere('tournament_name', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('current_period', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($teamId > 0) {
            $query->where(function (Builder $builder) use ($teamId): void {
                $builder->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            });
        }

        $sortColumn = match ($sortBy) {
            'match_code' => 'match_code',
            'status' => 'status',
            'scheduled_at' => 'scheduled_at',
            'started_at' => 'started_at',
            'completed_at' => 'completed_at',
            default => 'scheduled_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Sport matches retrieved successfully.',
            $paginator,
            $request,
            SportMatchResource::class,
        );
    }

    public function store(StoreSportMatchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $homeTeam = $this->resolveTeamReference($data['home_team']);
        $awayTeam = $this->resolveTeamReference($data['away_team']);

        if (! $homeTeam || ! $awayTeam) {
            return ApiResponse::errorResponse('One or both teams could not be found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($homeTeam->id === $awayTeam->id) {
            return ApiResponse::errorResponse('Home and away teams must be different.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $match = SportMatch::query()->create([
            'match_code' => $data['match_code'] ?? $this->makeSportCode('match'),
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'competition_type' => $data['competition_type'] ?? null,
            'tournament_name' => $data['tournament_name'] ?? null,
            'venue' => $data['venue'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => $data['status'],
            'current_period' => $data['current_period'] ?? null,
            'home_score' => 0,
            'away_score' => 0,
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $this->scoreService->recalculate($match);
        $match->loadMissing(['homeTeam', 'awayTeam'])->loadCount('events');

        return ApiResponse::successResponse(
            'Sport match created successfully.',
            [
                'match' => SportMatchResource::make($match)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $match = SportMatch::query()->with(['homeTeam', 'awayTeam', 'events.team', 'events.player'])->withCount('events')->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'Sport match retrieved successfully.',
            [
                'match' => SportMatchResource::make($match)->resolve($request),
                'events' => \App\Http\Resources\Sport\SportMatchEventResource::collection($match->events)->resolve($request),
            ],
        );
    }

    public function update(UpdateSportMatchRequest $request, string $id): JsonResponse
    {
        $match = SportMatch::query()->withCount('events')->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        $homeTeam = null;
        $awayTeam = null;

        if (array_key_exists('home_team', $data)) {
            $homeTeam = $this->resolveTeamReference($data['home_team']);
            if (! $homeTeam) {
                return ApiResponse::errorResponse('Home team not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (array_key_exists('away_team', $data)) {
            $awayTeam = $this->resolveTeamReference($data['away_team']);
            if (! $awayTeam) {
                return ApiResponse::errorResponse('Away team not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($homeTeam && $awayTeam && $homeTeam->id === $awayTeam->id) {
            return ApiResponse::errorResponse('Home and away teams must be different.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($match->events_count > 0 && (($homeTeam && $homeTeam->id !== $match->home_team_id) || ($awayTeam && $awayTeam->id !== $match->away_team_id))) {
            return ApiResponse::errorResponse('Teams cannot be changed after events exist.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach (['competition_type', 'tournament_name', 'venue', 'scheduled_at', 'current_period', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $match->{$field} = $data[$field];
            }
        }

        if (array_key_exists('home_team', $data) && $homeTeam) {
            $match->home_team_id = $homeTeam->id;
        }

        if (array_key_exists('away_team', $data) && $awayTeam) {
            $match->away_team_id = $awayTeam->id;
        }

        if (array_key_exists('match_code', $data)) {
            $match->match_code = $data['match_code'] ?: $this->makeSportCode('match');
        }

        if (array_key_exists('status', $data)) {
            $match->status = $data['status'];
        }

        $match->save();
        $this->scoreService->recalculate($match);
        $match->loadMissing(['homeTeam', 'awayTeam'])->loadCount('events');

        return ApiResponse::successResponse(
            'Sport match updated successfully.',
            [
                'match' => SportMatchResource::make($match)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $match = SportMatch::query()->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        $match->delete();

        return ApiResponse::successResponse('Sport match deleted successfully.');
    }

    public function updateStatus(UpdateSportMatchStatusRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $match = SportMatch::query()->withCount('events')->find($id);

        if (! $match) {
            return ApiResponse::errorResponse('Match not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        $nextStatus = $data['status'];
        $currentStatus = $match->status;

        if (! $this->isValidTransition($currentStatus, $nextStatus)) {
            return ApiResponse::errorResponse('Invalid match status transition.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $match->status = $nextStatus;

        if ($nextStatus === 'live' && ! $match->started_at) {
            $match->started_at = now();
            $match->current_period = $data['current_period'] ?? 'first_half';
        }

        if ($nextStatus === 'halftime') {
            $match->current_period = $data['current_period'] ?? 'halftime';
        }

        if ($nextStatus === 'completed') {
            $match->completed_at = now();
            $match->current_period = $data['current_period'] ?? 'final';
            $this->scoreService->recalculate($match);
        }

        if ($currentStatus === 'completed' && $nextStatus === 'live') {
            $match->completed_at = null;
            $match->current_period = $data['current_period'] ?? 'second_half';
        }

        if (in_array($nextStatus, ['draft', 'scheduled', 'postponed', 'cancelled'], true)) {
            $match->current_period = $data['current_period'] ?? null;
        }

        $match->save();
        $match->loadMissing(['homeTeam', 'awayTeam'])->loadCount('events');

        return ApiResponse::successResponse(
            'Match status updated successfully.',
            [
                'match' => SportMatchResource::make($match)->resolve($request),
            ],
        );
    }

    private function isValidTransition(string $current, string $next): bool
    {
        $map = [
            'draft' => ['scheduled', 'postponed', 'cancelled'],
            'scheduled' => ['live', 'postponed', 'cancelled'],
            'live' => ['halftime', 'completed', 'postponed', 'cancelled'],
            'halftime' => ['live', 'completed'],
            'completed' => ['live'],
            'postponed' => ['scheduled', 'cancelled'],
            'cancelled' => [],
        ];

        return in_array($next, $map[$current] ?? [], true);
    }
}

