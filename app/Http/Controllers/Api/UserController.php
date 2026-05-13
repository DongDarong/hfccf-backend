<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $search = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));
        $departmentCode = trim((string) $request->query('departmentCode', ''));

        $query = User::query()
            ->with(['department', 'role', 'permissions' => fn ($q) => $q->orderBy('permissions.code')])
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($role !== '') {
            $query->where('role_code', $role);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($departmentCode !== '') {
            $query->where('department_code', $departmentCode);
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => [
                'users' => UserResource::collection($users->getCollection())->resolve($request),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::query()->with('department')->where('code', $data['role'])->first();
        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role.',
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userId = $this->nextUserId();
        $departmentCode = $data['department_code'] ?? $role->department_code;
        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            $username = trim($data['first_name'].' '.$data['last_name']);
        }

        $avatarUrl = $this->storeAvatarIfUploaded($request);

        $user = User::query()->create([
            'id' => $userId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $username,
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role_code' => $role->code,
            'department_code' => $departmentCode,
            'bio' => $data['bio'] ?? null,
            'status' => $data['status'] ?? 'active',
            'avatar' => $avatarUrl,
            'password' => $data['password'],
        ]);
        $this->syncPermissionsFromRole($user, $role->code);

        $user->load(['department', 'role', 'permissions' => fn ($q) => $q->orderBy('permissions.code')]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $model = User::query()
            ->with(['department', 'role', 'permissions' => fn ($q) => $q->orderBy('permissions.code')])
            ->find($user);

        if (! $model) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => [
                'user' => (new UserResource($model))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdateUserRequest $request, string $user): JsonResponse
    {
        $model = User::query()->with(['role'])->find($user);
        if (! $model) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        $replaceAvatar = $request->hasFile('avatar');
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);

        if (array_key_exists('role', $data)) {
            $role = Role::query()->with('department')->where('code', $data['role'])->first();
            if (! $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role.',
                    'data' => null,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $model->role_code = $role->code;

            // If caller did not explicitly set department, keep it consistent with the role.
            if (! array_key_exists('department_code', $data)) {
                $model->department_code = $role->department_code;
            }
        }

        if (array_key_exists('department_code', $data)) {
            $model->department_code = $data['department_code'] ?: $model->department_code;
        }

        if (array_key_exists('first_name', $data)) {
            $model->first_name = $data['first_name'];
        }
        if (array_key_exists('last_name', $data)) {
            $model->last_name = $data['last_name'];
        }
        if (array_key_exists('username', $data)) {
            $username = trim((string) $data['username']);
            $model->username = $username !== '' ? $username : trim($model->first_name.' '.$model->last_name);
        }
        if (array_key_exists('email', $data)) {
            $model->email = strtolower($data['email']);
        }
        if (array_key_exists('phone', $data)) {
            $model->phone = $data['phone'];
        }
        if (array_key_exists('bio', $data)) {
            $model->bio = $data['bio'];
        }
        if (array_key_exists('status', $data)) {
            $model->status = $data['status'] ?? $model->status;
        }
        if (array_key_exists('password', $data) && $data['password']) {
            $model->password = $data['password'];
        }

        if ($replaceAvatar) {
            $this->deleteStoredAvatarIfNeeded($model->avatar);
            $model->avatar = $this->storeAvatarIfUploaded($request);
        } elseif ($removeAvatar) {
            $this->deleteStoredAvatarIfNeeded($model->avatar);
            $model->avatar = null;
        }

        $model->save();
        $this->syncPermissionsFromRole($model, $model->role_code);

        $model->load(['department', 'role', 'permissions' => fn ($q) => $q->orderBy('permissions.code')]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => [
                'user' => (new UserResource($model))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $user): JsonResponse
    {
        $model = User::query()->find($user);
        if (! $model) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $model->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function nextUserId(): string
    {
        $maxNumeric = User::withTrashed()
            ->where('id', 'like', 'usr_%')
            ->pluck('id')
            ->map(static function (string $id): int {
                return (int) preg_replace('/^usr_/', '', $id);
            })
            ->max() ?? 0;

        $next = $maxNumeric + 1;
        $suffix = $next <= 999 ? str_pad((string) $next, 3, '0', STR_PAD_LEFT) : (string) $next;

        return 'usr_'.$suffix;
    }

    private function storeAvatarIfUploaded(Request $request): ?string
    {
        if (! $request->hasFile('avatar')) {
            return null;
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        return asset('storage/'.$path);
    }

    private function deleteStoredAvatarIfNeeded(?string $avatarUrl): void
    {
        $path = $this->resolvePublicStoragePath($avatarUrl);

        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function resolvePublicStoragePath(?string $avatarUrl): ?string
    {
        $value = trim((string) $avatarUrl);
        if ($value === '') {
            return null;
        }

        $path = (string) parse_url($value, PHP_URL_PATH);
        $storagePrefix = '/storage/';

        if ($path === '' || ! str_contains($path, $storagePrefix)) {
            return null;
        }

        return substr($path, strpos($path, $storagePrefix) + strlen($storagePrefix));
    }

    private function syncPermissionsFromRole(User $user, string $roleCode): void
    {
        $role = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('permissions.code')])
            ->where('code', $roleCode)
            ->first();

        $permissionCodes = $role?->permissions->pluck('code')->values()->all() ?? [];

        $user->permissions()->sync($permissionCodes);
    }
}
