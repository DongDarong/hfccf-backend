<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolTeacherRequest;
use App\Http\Requests\Preschool\UpdatePreschoolTeacherRequest;
use App\Http\Resources\Preschool\PreschoolAttendanceResource;
use App\Http\Resources\Preschool\PreschoolClassResource;
use App\Http\Resources\Preschool\PreschoolStudentResource;
use App\Http\Resources\UserResource;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\Role;
use App\Models\User;
use App\Support\ImageStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolTeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
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
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = $this->teacherQuery();

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('status', 'like', $like);
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

        return response()->json([
            'success' => true,
            'message' => 'Preschool teachers retrieved successfully.',
            'data' => [
                'items' => UserResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolTeacherRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();
        $role = $this->teacherRole();
        $userId = $this->nextUserId();
        $username = trim((string) ($data['username'] ?? ''));

        if ($username === '') {
            $username = trim($data['first_name'].' '.$data['last_name']);
        }

        $teacher = User::query()->create([
            'id' => $userId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $username,
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role_code' => 'teacher-preschool',
            'department_code' => 'education',
            'bio' => $data['bio'] ?? null,
            'status' => $data['status'] ?? 'active',
            'avatar' => $this->storeAvatarIfUploaded($request),
            'password' => $data['password'],
        ]);

        $this->syncPermissionsFromRole($teacher, $role->code);
        $teacher->load(['department', 'role', 'permissions' => fn ($q) => $q->orderBy('permissions.code')]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool teacher created successfully.',
            'data' => [
                'user' => UserResource::make($teacher)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $teacher = $this->teacherQuery()->find($id);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool teacher retrieved successfully.',
            'data' => [
                'user' => UserResource::make($teacher)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolTeacherRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $teacher = $this->teacherQuery()->find($id);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        $replaceAvatar = $request->hasFile('avatar');
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);

        if (array_key_exists('first_name', $data)) {
            $teacher->first_name = $data['first_name'];
        }
        if (array_key_exists('last_name', $data)) {
            $teacher->last_name = $data['last_name'];
        }
        if (array_key_exists('username', $data)) {
            $teacher->username = trim((string) $data['username']) ?: trim($teacher->first_name.' '.$teacher->last_name);
        }
        if (array_key_exists('email', $data)) {
            $teacher->email = strtolower($data['email']);
        }
        if (array_key_exists('phone', $data)) {
            $teacher->phone = $data['phone'];
        }
        if (array_key_exists('bio', $data)) {
            $teacher->bio = $data['bio'];
        }
        if (array_key_exists('status', $data)) {
            $teacher->status = $data['status'] ?? $teacher->status;
        }
        if ($replaceAvatar) {
            $this->deleteStoredAvatarIfNeeded($teacher->avatar);
            $teacher->avatar = $this->storeAvatarIfUploaded($request);
        } elseif ($removeAvatar) {
            $this->deleteStoredAvatarIfNeeded($teacher->avatar);
            $teacher->avatar = null;
        }

        $teacher->save();
        $this->syncPermissionsFromRole($teacher, 'teacher-preschool');
        $teacher->load(['department', 'role', 'permissions' => fn ($q) => $q->orderBy('permissions.code')]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool teacher updated successfully.',
            'data' => [
                'user' => UserResource::make($teacher)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $teacher = $this->teacherQuery()->find($id);

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        PreschoolClass::query()
            ->where('teacher_user_id', $teacher->id)
            ->update([
                'teacher_user_id' => null,
                'teacher_display_name' => $teacher->username ?: trim($teacher->first_name.' '.$teacher->last_name),
            ]);

        $teacher->delete();

        return response()->json([
            'success' => true,
            'message' => 'Preschool teacher deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function myStudents(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTeacherViewer($request->user())) {
            return $response;
        }

        $user = $request->user();
        $query = $this->studentQueryForUser($user);

        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        $paginator = $query
            ->with(['classes' => fn ($q) => $q->orderBy('code')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Preschool students retrieved successfully.',
            'data' => [
                'items' => PreschoolStudentResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function myClasses(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTeacherViewer($request->user())) {
            return $response;
        }

        $user = $request->user();
        $query = $this->classQueryForUser($user);

        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        $paginator = $query
            ->with(['teacher', 'students'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Preschool classes retrieved successfully.',
            'data' => [
                'items' => PreschoolClassResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function myAttendance(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTeacherViewer($request->user())) {
            return $response;
        }

        $user = $request->user();
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        $query = PreschoolAttendanceRecord::query()->with(['student', 'preschoolClass', 'recordedBy']);

        if ($user->role_code === 'teacher-preschool') {
            $teacherClassIds = PreschoolClass::query()
                ->where('teacher_user_id', $user->id)
                ->pluck('id')
                ->all();
            $query->whereIn('class_id', $teacherClassIds);
        }

        $paginator = $query
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance retrieved successfully.',
            'data' => [
                'items' => PreschoolAttendanceResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    private function teacherQuery(): Builder
    {
        return User::query()
            ->with(['department', 'role', 'permissions' => fn ($query) => $query->orderBy('permissions.code')])
            ->whereNull('deleted_at')
            ->where('role_code', 'teacher-preschool');
    }

    private function studentQueryForUser(User $user): Builder
    {
        $query = PreschoolStudent::query()->whereNull('deleted_at');

        if ($user->role_code === 'teacher-preschool') {
            $query->whereHas('classes', static function (Builder $builder) use ($user): void {
                $builder->where('teacher_user_id', $user->id);
            });
        }

        return $query;
    }

    private function classQueryForUser(User $user): Builder
    {
        $query = PreschoolClass::query()->whereNull('deleted_at');

        if ($user->role_code === 'teacher-preschool') {
            $query->where('teacher_user_id', $user->id);
        }

        return $query;
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function authorizeTeacherViewer(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function teacherRole(): Role
    {
        return Role::query()->with('permissions')->where('code', 'teacher-preschool')->firstOrFail();
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

        return 'usr_'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function storeAvatarIfUploaded(Request $request): ?string
    {
        return ImageStorage::store($request->file('avatar'), 'avatars');
    }

    private function deleteStoredAvatarIfNeeded(?string $avatarUrl): void
    {
        ImageStorage::delete($avatarUrl);
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

    private function paginationShape($paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
