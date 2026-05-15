<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportPlayerRequest;
use App\Http\Requests\Sport\UpdateSportPlayerRequest;
use App\Http\Resources\Sport\SportPlayerResource;
use App\Models\SportPlayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportPlayerController extends SportController
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
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $division = trim((string) ($validated['division'] ?? ''));
        $teamId = (int) ($validated['team_id'] ?? 0);
        $position = trim((string) ($validated['position'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = SportPlayer::query()->with(['team']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('player_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('primary_position', 'like', $like)
                    ->orWhere('registration_status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($division !== '') {
            $query->where('division', $division);
        }

        if ($teamId > 0) {
            $query->where('team_id', $teamId);
        }

        if ($position !== '') {
            $query->where('primary_position', $position);
        }

        $sortColumn = match ($sortBy) {
            'player_code' => 'player_code',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'status' => 'status',
            'created_at' => 'created_at',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return \App\Support\ApiResponse::paginatedResponse(
            'Sport players retrieved successfully.',
            $paginator,
            $request,
            SportPlayerResource::class,
        );
    }

    public function store(StoreSportPlayerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $team = $this->resolveTeamReference($data['team']);

        if (! $team) {
            return \App\Support\ApiResponse::errorResponse('Team not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $player = SportPlayer::query()->create([
            'player_code' => $data['player_code'] ?? $this->makeSportCode('player'),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'jersey_number' => $data['jersey_number'] ?? null,
            'position' => $data['position'] ?? null,
            'team_id' => $team->id,
            'division' => $data['division'] ?? $team->division,
            'gender' => $data['gender'] ?? null,
            'age' => $data['age'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'phone' => $data['phone'] ?? null,
            'photo' => $this->storeSportFile($request->file('photo'), 'sport/players'),
            'height_cm' => $data['height_cm'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'preferred_foot' => $data['preferred_foot'] ?? null,
            'blood_type' => $data['blood_type'] ?? null,
            'village' => $data['village'] ?? null,
            'commune' => $data['commune'] ?? null,
            'district' => $data['district'] ?? null,
            'province' => $data['province'] ?? null,
            'current_school' => $data['current_school'] ?? null,
            'grade_year' => $data['grade_year'] ?? null,
            'primary_position' => $data['primary_position'] ?? null,
            'registration_status' => $data['registration_status'] ?? 'registered',
            'matches_played' => (int) ($data['matches_played'] ?? 0),
            'goals_scored' => (int) ($data['goals_scored'] ?? 0),
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        $player->loadMissing(['team']);

        return \App\Support\ApiResponse::successResponse(
            'Sport player created successfully.',
            [
                'player' => SportPlayerResource::make($player)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $player = SportPlayer::query()->with(['team'])->find($id);

        if (! $player) {
            return \App\Support\ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        return \App\Support\ApiResponse::successResponse(
            'Sport player retrieved successfully.',
            [
                'player' => SportPlayerResource::make($player)->resolve($request),
            ],
        );
    }

    public function update(UpdateSportPlayerRequest $request, string $id): JsonResponse
    {
        $player = SportPlayer::query()->find($id);

        if (! $player) {
            return \App\Support\ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        $team = null;

        if (array_key_exists('team', $data)) {
            $team = $this->resolveTeamReference($data['team']);

            if (! $team) {
                return \App\Support\ApiResponse::errorResponse('Team not found.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        foreach ([
            'first_name',
            'last_name',
            'position',
            'division',
            'gender',
            'age',
            'date_of_birth',
            'phone',
            'height_cm',
            'weight_kg',
            'preferred_foot',
            'blood_type',
            'village',
            'commune',
            'district',
            'province',
            'current_school',
            'grade_year',
            'primary_position',
            'registration_status',
            'matches_played',
            'goals_scored',
            'status',
            'notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $player->{$field} = $data[$field];
            }
        }

        if (array_key_exists('team', $data) && $team) {
            $player->team_id = $team->id;
            if (! array_key_exists('division', $data) || trim((string) $data['division']) === '') {
                $player->division = $team->division;
            }
        }

        if (array_key_exists('player_code', $data)) {
            $player->player_code = $data['player_code'] ?: $this->makeSportCode('player');
        }

        if ($request->hasFile('photo')) {
            $this->deleteSportFile($player->photo);
            $player->photo = $this->storeSportFile($request->file('photo'), 'sport/players');
        } elseif ($request->boolean('remove_photo')) {
            $this->deleteSportFile($player->photo);
            $player->photo = null;
        }

        $player->save();
        $player->loadMissing(['team']);

        return \App\Support\ApiResponse::successResponse(
            'Sport player updated successfully.',
            [
                'player' => SportPlayerResource::make($player)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $player = SportPlayer::query()->find($id);

        if (! $player) {
            return \App\Support\ApiResponse::errorResponse('Player not found.', null, Response::HTTP_NOT_FOUND);
        }

        $this->deleteSportFile($player->photo);
        $player->delete();

        return \App\Support\ApiResponse::successResponse('Sport player deleted successfully.');
    }
}

