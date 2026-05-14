<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportTeamRequest;
use App\Http\Requests\Sport\UpdateSportTeamRequest;
use App\Http\Resources\Sport\SportTeamResource;
use App\Models\SportTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTeamController extends SportController
{
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
            'division' => ['sometimes', 'nullable', 'string', 'max:100'],
            'coach_user_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $division = trim((string) ($validated['division'] ?? ''));
        $coachUserId = trim((string) ($validated['coach_user_id'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = SportTeam::query()->with(['coach']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('team_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('short_name', 'like', $like)
                    ->orWhere('coach_display_name', 'like', $like)
                    ->orWhere('division', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($division !== '') {
            $query->where('division', $division);
        }

        if ($coachUserId !== '') {
            $query->where('coach_user_id', $coachUserId);
        }

        $sortColumn = match ($sortBy) {
            'team_code' => 'team_code',
            'name' => 'name',
            'division' => 'division',
            'status' => 'status',
            'points' => 'points',
            'wins' => 'wins',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return \App\Support\ApiResponse::paginatedResponse(
            'Sport teams retrieved successfully.',
            $paginator,
            $request,
            SportTeamResource::class,
        );
    }

    public function store(StoreSportTeamRequest $request): JsonResponse
    {
        $data = $request->validated();

        $team = SportTeam::query()->create([
            'team_code' => $data['team_code'] ?? $this->makeSportCode('team'),
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'coach_user_id' => $this->resolveUserReference($data['coach_user_id'] ?? $data['coach'] ?? null, 'coach')?->id,
            'coach_display_name' => $this->resolveCoachDisplayName($data),
            'division' => $data['division'] ?? null,
            'captain_name' => $data['captain_name'] ?? null,
            'players_count' => (int) ($data['players_count'] ?? 0),
            'matches_count' => (int) ($data['matches_count'] ?? 0),
            'wins' => (int) ($data['wins'] ?? 0),
            'draws' => (int) ($data['draws'] ?? 0),
            'losses' => (int) ($data['losses'] ?? 0),
            'points' => $this->resolvePoints($data),
            'venue' => $data['venue'] ?? null,
            'logo' => $this->storeSportFile($request->file('logo'), 'sport/teams'),
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
        ]);

        $team->loadMissing(['coach']);

        return \App\Support\ApiResponse::successResponse(
            'Sport team created successfully.',
            [
                'team' => SportTeamResource::make($team)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $team = SportTeam::query()->with(['coach'])->find($id);

        if (! $team) {
            return \App\Support\ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        return \App\Support\ApiResponse::successResponse(
            'Sport team retrieved successfully.',
            [
                'team' => SportTeamResource::make($team)->resolve($request),
            ],
        );
    }

    public function update(UpdateSportTeamRequest $request, string $id): JsonResponse
    {
        $team = SportTeam::query()->find($id);

        if (! $team) {
            return \App\Support\ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['name', 'short_name', 'division', 'captain_name', 'venue', 'status', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $team->{$field} = $data[$field];
            }
        }

        if (array_key_exists('coach_user_id', $data) || array_key_exists('coach', $data)) {
            $coach = $this->resolveUserReference($data['coach_user_id'] ?? $data['coach'] ?? null, 'coach');
            $team->coach_user_id = $coach?->id;
            $team->coach_display_name = $this->resolveCoachDisplayName($data, $team->coach_display_name, $coach);
        }

        if (array_key_exists('players_count', $data)) {
            $team->players_count = max(0, (int) $data['players_count']);
        }

        if (array_key_exists('matches_count', $data)) {
            $team->matches_count = max(0, (int) $data['matches_count']);
        }

        if (array_key_exists('wins', $data)) {
            $team->wins = max(0, (int) $data['wins']);
        }

        if (array_key_exists('draws', $data)) {
            $team->draws = max(0, (int) $data['draws']);
        }

        if (array_key_exists('losses', $data)) {
            $team->losses = max(0, (int) $data['losses']);
        }

        if (array_key_exists('points', $data)) {
            $team->points = max(0, (int) $data['points']);
        } else {
            $team->points = $this->resolvePoints($team->toArray());
        }

        if ($request->hasFile('logo')) {
            $this->deleteSportFile($team->logo);
            $team->logo = $this->storeSportFile($request->file('logo'), 'sport/teams');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteSportFile($team->logo);
            $team->logo = null;
        }

        if (! $team->team_code) {
            $team->team_code = $this->makeSportCode('team');
        }

        $team->save();
        $team->loadMissing(['coach']);

        return \App\Support\ApiResponse::successResponse(
            'Sport team updated successfully.',
            [
                'team' => SportTeamResource::make($team)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $team = SportTeam::query()->find($id);

        if (! $team) {
            return \App\Support\ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        $this->deleteSportFile($team->logo);
        $team->delete();

        return \App\Support\ApiResponse::successResponse('Sport team deleted successfully.');
    }

    private function resolveCoachDisplayName(array $data, ?string $fallback = null, ?\App\Models\User $coach = null): ?string
    {
        $coachDisplayName = trim((string) ($data['coach_display_name'] ?? $data['coach'] ?? $fallback ?? ''));

        if ($coachDisplayName !== '') {
            return $coachDisplayName;
        }

        if ($coach) {
            return trim($coach->first_name.' '.$coach->last_name);
        }

        return null;
    }

    private function resolvePoints(array|SportTeam $data): int
    {
        if (is_array($data)) {
            $wins = (int) ($data['wins'] ?? 0);
            $draws = (int) ($data['draws'] ?? 0);
            $points = $data['points'] ?? null;
        } else {
            $wins = (int) $data->wins;
            $draws = (int) $data->draws;
            $points = $data->points;
        }

        return $points !== null ? max(0, (int) $points) : max(0, ($wins * 3) + $draws);
    }
}
