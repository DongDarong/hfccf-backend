<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportCoachRequest;
use App\Http\Requests\Sport\UpdateSportCoachRequest;
use App\Http\Resources\UserResource;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SportCoachController extends SportController
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
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = User::query()
            ->with(['role', 'department', 'permissions'])
            ->where('role_code', 'coach');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $sortColumn = match ($sortBy) {
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Sport coaches retrieved successfully.',
            $paginator,
            $request,
            UserResource::class,
        );
    }

    public function store(StoreSportCoachRequest $request): JsonResponse
    {
        $data = $request->validated();

        $coach = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'] ?: trim($data['first_name'].' '.$data['last_name']),
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role_code' => 'coach',
            'department_code' => 'sports',
            'status' => $data['status'],
            'password' => $data['password'],
            'avatar' => $this->storeSportFile($request->file('avatar'), 'sport/coaches'),
        ]);

        $this->syncRolePermissions($coach, 'coach');
        $coach->loadMissing(['role', 'department', 'permissions']);

        return ApiResponse::successResponse(
            'Sport coach created successfully.',
            [
                'coach' => UserResource::make($coach)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $coach = User::query()
            ->with(['role', 'department', 'permissions'])
            ->where('role_code', 'coach')
            ->find($id);

        if (! $coach) {
            return ApiResponse::errorResponse('Coach not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'Sport coach retrieved successfully.',
            [
                'coach' => UserResource::make($coach)->resolve($request),
            ],
        );
    }

    public function update(UpdateSportCoachRequest $request, string $id): JsonResponse
    {
        $coach = User::query()->where('role_code', 'coach')->find($id);

        if (! $coach) {
            return ApiResponse::errorResponse('Coach not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        foreach (['first_name', 'last_name', 'username', 'phone', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $coach->{$field} = $data[$field];
            }
        }

        if (array_key_exists('email', $data)) {
            $coach->email = strtolower($data['email']);
        }

        if (! empty($data['password'] ?? null)) {
            $coach->password = $data['password'];
        }

        if ($request->hasFile('avatar')) {
            $this->deleteSportFile($coach->avatar);
            $coach->avatar = $this->storeSportFile($request->file('avatar'), 'sport/coaches');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteSportFile($coach->avatar);
            $coach->avatar = null;
        }

        if (! $coach->username) {
            $coach->username = trim($coach->first_name.' '.$coach->last_name);
        }

        $coach->role_code = 'coach';
        $coach->department_code = 'sports';
        $coach->save();

        $this->syncRolePermissions($coach, 'coach');
        $coach->loadMissing(['role', 'department', 'permissions']);

        return ApiResponse::successResponse(
            'Sport coach updated successfully.',
            [
                'coach' => UserResource::make($coach)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $coach = User::query()->where('role_code', 'coach')->find($id);

        if (! $coach) {
            return ApiResponse::errorResponse('Coach not found.', null, Response::HTTP_NOT_FOUND);
        }

        SportTeam::query()
            ->where('coach_user_id', $coach->id)
            ->update([
                'coach_display_name' => trim($coach->first_name.' '.$coach->last_name),
                'coach_user_id' => null,
            ]);

        $coach->delete();

        return ApiResponse::successResponse('Sport coach deleted successfully.');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $coach = $request->user();

        if (! $coach || $coach->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $teams = SportTeam::query()->where('coach_user_id', $coach->id)->get();

        $recentMatches = \App\Models\SportMatch::query()
            ->with(['homeTeam', 'awayTeam', 'events'])
            ->where(function ($query) use ($coach): void {
                $query->whereHas('homeTeam', fn ($builder) => $builder->where('coach_user_id', $coach->id))
                    ->orWhereHas('awayTeam', fn ($builder) => $builder->where('coach_user_id', $coach->id));
            })
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $liveMatches = $recentMatches->where('status', 'live')->count();
        $upcomingMatches = $recentMatches->whereIn('status', ['draft', 'scheduled'])->count();

        return ApiResponse::successResponse('Coach dashboard retrieved successfully.', [
            'summary' => [
                'teams' => $teams->count(),
                'matches' => $recentMatches->count(),
                'liveMatches' => $liveMatches,
                'upcomingMatches' => $upcomingMatches,
            ],
            'teams' => \App\Http\Resources\Sport\SportTeamResource::collection($teams)->resolve($request),
            'matches' => \App\Http\Resources\Sport\SportMatchResource::collection($recentMatches)->resolve($request),
        ]);
    }

    public function teams(Request $request): JsonResponse
    {
        $coach = $request->user();

        if (! $coach || $coach->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $teams = SportTeam::query()
            ->with(['coach'])
            ->where('coach_user_id', $coach->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::successResponse('Coach teams retrieved successfully.', [
            'items' => \App\Http\Resources\Sport\SportTeamResource::collection($teams)->resolve($request),
            'pagination' => [
                'page' => 1,
                'perPage' => $teams->count() ?: 10,
                'total' => $teams->count(),
                'totalPages' => 1,
            ],
        ]);
    }

    public function matches(Request $request): JsonResponse
    {
        $coach = $request->user();

        if (! $coach || $coach->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $matches = \App\Models\SportMatch::query()
            ->with(['homeTeam', 'awayTeam', 'events'])
            ->where(function ($query) use ($coach): void {
                $query->whereHas('homeTeam', fn ($builder) => $builder->where('coach_user_id', $coach->id))
                    ->orWhereHas('awayTeam', fn ($builder) => $builder->where('coach_user_id', $coach->id));
            })
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::successResponse('Coach matches retrieved successfully.', [
            'items' => \App\Http\Resources\Sport\SportMatchResource::collection($matches)->resolve($request),
            'pagination' => [
                'page' => 1,
                'perPage' => $matches->count() ?: 10,
                'total' => $matches->count(),
                'totalPages' => 1,
            ],
        ]);
    }

    private function syncRolePermissions(User $user, string $roleCode): void
    {
        $permissionCodes = DB::table('role_permissions')
            ->where('role_code', $roleCode)
            ->pluck('permission_code')
            ->all();

        DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->delete();

        if ($permissionCodes === []) {
            return;
        }

        DB::table('user_permissions')->insert(array_map(
            static fn (string $permissionCode): array => [
                'user_id' => $user->id,
                'permission_code' => $permissionCode,
            ],
            $permissionCodes,
        ));
    }
}
