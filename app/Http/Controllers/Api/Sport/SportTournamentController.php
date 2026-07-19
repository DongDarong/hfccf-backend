<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\AttachSportTournamentTeamRequest;
use App\Http\Requests\Sport\StoreSportTournamentRequest;
use App\Http\Requests\Sport\UpdateSportTournamentRequest;
use App\Http\Resources\Sport\SportStandingResource;
use App\Http\Resources\Sport\SportTournamentResource;
use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportStanding;
use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Models\SportTournamentGroup;
use App\Models\SportTournamentGroupTeam;
use App\Models\SportTournamentKnockoutRound;
use App\Support\ApiResponse;
use App\Support\SportStandingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SportTournamentController extends SportController
{
    public function __construct(private readonly SportStandingsService $standingsService) {}

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
            'type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $type = trim((string) ($validated['type'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = SportTournament::query()->withCount(['teams', 'matches', 'standings', 'groups', 'knockoutRounds', 'matchEvents']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('tournament_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('season', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('tournament_type', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($type !== '') {
            $query->where('tournament_type', $type);
        }

        $sortColumn = match ($sortBy) {
            'tournament_code' => 'tournament_code',
            'name' => 'name',
            'season' => 'season',
            'status' => 'status',
            'starts_at' => 'starts_at',
            'ends_at' => 'ends_at',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Sport tournaments retrieved successfully.',
            $paginator,
            $request,
            SportTournamentResource::class,
        );
    }

    public function store(StoreSportTournamentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tournament = SportTournament::query()->create([
            'tournament_code' => $data['tournament_code'] ?? $this->makeSportCode('tournament'),
            'slug' => $data['slug'] ?? null,
            'name' => $data['name'],
            'season' => $data['season'] ?? null,
            'tournament_type' => $data['tournament_type'] ?? 'league',
            'status' => $data['status'],
            'visibility' => $data['visibility'] ?? 'private',
            'registration_open_at' => $data['registration_open_at'] ?? null,
            'registration_close_at' => $data['registration_close_at'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'description' => $data['description'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'banner_path' => $data['banner_path'] ?? null,
            'location' => $data['location'] ?? null,
            'organizer' => $data['organizer'] ?? null,
            'rules' => $data['rules'] ?? null,
            'settings' => $data['settings'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        $tournament->loadCount(['teams', 'matches', 'standings', 'groups', 'knockoutRounds', 'matchEvents']);

        return ApiResponse::successResponse(
            'Sport tournament created successfully.',
            [
                'tournament' => SportTournamentResource::make($tournament)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()
            ->with(['teams.coach'])
            ->withCount(['teams', 'matches', 'standings', 'groups', 'knockoutRounds', 'matchEvents'])
            ->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'Sport tournament retrieved successfully.',
            [
                'tournament' => SportTournamentResource::make($tournament)->resolve($request),
            ],
        );
    }

    public function update(UpdateSportTournamentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['name', 'season', 'status', 'visibility', 'registration_open_at', 'registration_close_at', 'starts_at', 'ends_at', 'description', 'logo_path', 'banner_path', 'location', 'organizer', 'rules', 'settings', 'slug'] as $field) {
            if (array_key_exists($field, $data)) {
                $tournament->{$field} = $data[$field];
            }
        }

        if (array_key_exists('tournament_code', $data)) {
            $tournament->tournament_code = $data['tournament_code'] ?: $this->makeSportCode('tournament');
        }

        if (array_key_exists('tournament_type', $data) && $data['tournament_type']) {
            $tournament->tournament_type = $data['tournament_type'];
        }

        $tournament->save();
        $tournament->loadCount(['teams', 'matches', 'standings', 'groups', 'knockoutRounds', 'matchEvents']);

        return ApiResponse::successResponse(
            'Sport tournament updated successfully.',
            [
                'tournament' => SportTournamentResource::make($tournament)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        SportStanding::query()->where('tournament_id', $tournament->id)->delete();
        SportMatchEvent::query()->where('tournament_id', $tournament->id)->delete();
        SportMatch::query()->where('tournament_id', $tournament->id)->delete();
        SportTournamentGroupTeam::query()->where('tournament_id', $tournament->id)->delete();
        SportTournamentGroup::query()->where('tournament_id', $tournament->id)->delete();
        SportTournamentKnockoutRound::query()->where('tournament_id', $tournament->id)->delete();
        $tournament->teams()->detach();
        $tournament->delete();

        return ApiResponse::successResponse('Sport tournament deleted successfully.');
    }

    public function addTeam(AttachSportTournamentTeamRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->with(['teams.coach'])->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $team = SportTeam::query()->find($request->validated()['team_id']);

        if (! $team) {
            return ApiResponse::errorResponse('Team not found.', null, Response::HTTP_NOT_FOUND);
        }

        $tournament->teams()->syncWithoutDetaching([
            $team->id => ['joined_at' => Carbon::now()],
        ]);

        $standings = $this->standingsService->rebuildTournament($tournament);
        $tournament->load(['teams.coach']);
        $tournament->loadCount(['teams', 'matches', 'standings', 'groups', 'knockoutRounds', 'matchEvents']);

        return ApiResponse::successResponse('Team added to tournament successfully.', [
            'tournament' => SportTournamentResource::make($tournament)->resolve($request),
            'standings' => SportStandingResource::collection($standings)->resolve($request),
        ]);
    }

    public function removeTeam(Request $request, string $id, string $teamId): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $tournament->teams()->detach($teamId);
        SportStanding::query()
            ->where('tournament_id', $tournament->id)
            ->where('team_id', $teamId)
            ->delete();

        $standings = $this->standingsService->rebuildTournament($tournament);
        $tournament->load(['teams.coach']);
        $tournament->loadCount(['teams', 'matches', 'standings', 'groups', 'knockoutRounds', 'matchEvents']);

        return ApiResponse::successResponse('Team removed from tournament successfully.', [
            'tournament' => SportTournamentResource::make($tournament)->resolve($request),
            'standings' => SportStandingResource::collection($standings)->resolve($request),
        ]);
    }

    public function standings(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportCoach($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->with(['teams.coach'])->withCount(['teams', 'matches', 'standings'])->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($this->isSportCoach($request->user()) && ! $this->canAccessTournament($request->user(), $tournament)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $this->standingsService->rebuildTournament($tournament);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = SportStanding::query()
            ->with(['team'])
            ->where('tournament_id', $tournament->id)
            ->orderBy('rank_position')
            ->orderBy('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Tournament standings retrieved successfully.',
            $paginator,
            $request,
            SportStandingResource::class,
        );
    }

    public function recalculateStandings(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $tournament = SportTournament::query()->find($id);

        if (! $tournament) {
            return ApiResponse::errorResponse('Tournament not found.', null, Response::HTTP_NOT_FOUND);
        }

        $standings = $this->standingsService->rebuildTournament($tournament);

        return ApiResponse::successResponse('Tournament standings recalculated successfully.', [
            'tournamentId' => $tournament->id,
            'standings' => SportStandingResource::collection($standings)->resolve($request),
        ]);
    }
}
