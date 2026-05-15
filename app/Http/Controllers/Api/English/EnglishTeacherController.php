<?php

namespace App\Http\Controllers\Api\English;

use App\Http\Controllers\Controller;
use App\Http\Requests\English\StoreEnglishTeacherRequest;
use App\Http\Requests\English\UpdateEnglishTeacherRequest;
use App\Http\Resources\English\EnglishClassResource;
use App\Http\Resources\English\EnglishStudentResource;
use App\Http\Resources\English\EnglishTaskResource;
use App\Http\Resources\UserResource;
use App\Models\EnglishClass;
use App\Models\EnglishStudent;
use App\Models\EnglishTask;
use App\Models\Role;
use App\Models\User;
use App\Services\EnglishService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnglishTeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
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

        $query = User::query()
            ->with(['role', 'department', 'permissions'])
            ->where('role_code', 'teacher-english');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('id', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
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
            'id' => 'id',
            'name' => 'first_name',
            'email' => 'email',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English teachers retrieved successfully.',
            $paginator,
            $request,
            UserResource::class,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $teacher = User::query()
            ->with(['role', 'department', 'permissions'])
            ->where('role_code', 'teacher-english')
            ->find($id);

        if (! $teacher) {
            return ApiResponse::errorResponse('Teacher not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'English teacher retrieved successfully.',
            [
                'user' => UserResource::make($teacher)->resolve($request),
            ],
        );
    }

    public function store(StoreEnglishTeacherRequest $request): JsonResponse
    {
        $teacher = $this->persistTeacher(null, $request->validated(), $request);

        return ApiResponse::successResponse(
            'English teacher created successfully.',
            [
                'user' => UserResource::make($teacher)->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateEnglishTeacherRequest $request, string $id): JsonResponse
    {
        $teacher = User::query()
            ->with(['role', 'department', 'permissions'])
            ->where('role_code', 'teacher-english')
            ->find($id);

        if (! $teacher) {
            return ApiResponse::errorResponse('Teacher not found.', null, Response::HTTP_NOT_FOUND);
        }

        $teacher = $this->persistTeacher($teacher, $request->validated(), $request);

        return ApiResponse::successResponse(
            'English teacher updated successfully.',
            [
                'user' => UserResource::make($teacher)->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        $teacher = User::query()
            ->where('role_code', 'teacher-english')
            ->find($id);

        if (! $teacher) {
            return ApiResponse::errorResponse('Teacher not found.', null, Response::HTTP_NOT_FOUND);
        }

        $teacher->delete();

        return ApiResponse::successResponse('English teacher deleted successfully.', null);
    }

    public function dashboard(Request $request, EnglishService $englishService): JsonResponse
    {
        if ($response = $this->authorizeEnglishTeacherAccess($request->user())) {
            return $response;
        }

        return ApiResponse::successResponse(
            'English teacher dashboard retrieved successfully.',
            $englishService->dashboardSummary($request->user()),
        );
    }

    public function classes(Request $request): JsonResponse
    {
        if ($response = $this->authorizeEnglishTeacherAccess($request->user())) {
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

        $user = $request->user();
        $query = EnglishClass::query()
            ->with(['teacher', 'students'])
            ->withCount(['students', 'tasks']);

        if ($user?->role_code === 'teacher-english') {
            $query->where('teacher_user_id', $user->id);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('class_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('level', 'like', $like)
                    ->orWhere('room', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $sortColumn = match ($sortBy) {
            'class_code' => 'class_code',
            'name' => 'name',
            'level' => 'level',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English classes retrieved successfully.',
            $paginator,
            $request,
            EnglishClassResource::class,
        );
    }

    public function students(Request $request): JsonResponse
    {
        if ($response = $this->authorizeEnglishTeacherAccess($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $gender = trim((string) ($validated['gender'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $user = $request->user();
        $query = EnglishStudent::query()->withCount(['classes', 'submissions']);

        if ($user?->role_code === 'teacher-english') {
            $query->whereHas('classes', function (Builder $builder) use ($user): void {
                $builder->where('teacher_user_id', $user->id);
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('student_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('guardian_name', 'like', $like)
                    ->orWhere('guardian_phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($gender !== '') {
            $query->where('gender', $gender);
        }

        $sortColumn = match ($sortBy) {
            'student_code' => 'student_code',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'status' => 'status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English students retrieved successfully.',
            $paginator,
            $request,
            EnglishStudentResource::class,
        );
    }

    public function tasks(Request $request): JsonResponse
    {
        if ($response = $this->authorizeEnglishTeacherAccess($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:english_classes,id'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $classId = (int) ($validated['class_id'] ?? 0);
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $user = $request->user();
        $query = EnglishTask::query()->with(['class', 'assignedBy'])->withCount('submissions');

        if ($user?->role_code === 'teacher-english') {
            $query->whereHas('class', function (Builder $builder) use ($user): void {
                $builder->where('teacher_user_id', $user->id);
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('task_status', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('task_status', $status);
        }

        if ($classId > 0) {
            $query->where('class_id', $classId);
        }

        $sortColumn = match ($sortBy) {
            'title' => 'title',
            'due_date' => 'due_date',
            'task_status' => 'task_status',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'English tasks retrieved successfully.',
            $paginator,
            $request,
            EnglishTaskResource::class,
        );
    }

    private function persistTeacher(?User $teacher, array $data, Request $request): User
    {
        $role = Role::query()->with('permissions')->findOrFail('teacher-english');

        if (! $teacher) {
            $teacher = User::query()->create([
                'id' => $this->nextUserId(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => trim((string) ($data['username'] ?? '')) ?: trim($data['first_name'].' '.$data['last_name']),
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role_code' => $role->code,
                'department_code' => $role->department_code ?? 'education',
                'status' => $data['status'] ?? 'active',
                'password' => $data['password'],
            ]);
        } else {
            foreach (['first_name', 'last_name', 'phone', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $teacher->{$field} = $data[$field];
                }
            }

            if (array_key_exists('username', $data)) {
                $teacher->username = trim((string) $data['username']) ?: trim(($data['first_name'] ?? $teacher->first_name).' '.($data['last_name'] ?? $teacher->last_name));
            }

            if (array_key_exists('email', $data)) {
                $teacher->email = strtolower((string) $data['email']);
            }

            if (array_key_exists('password', $data) && $data['password']) {
                $teacher->password = $data['password'];
            }

            $teacher->role_code = $role->code;
            $teacher->department_code = $role->department_code ?? 'education';
            $teacher->save();
        }

        $this->syncRolePermissions($teacher, $role);

        return $teacher->loadMissing(['role', 'department', 'permissions']);
    }

    private function syncRolePermissions(User $user, Role $role): void
    {
        DB::table('user_permissions')->where('user_id', $user->id)->delete();

        $rows = $role->permissions->map(static fn ($permission) => [
            'user_id' => $user->id,
            'permission_code' => $permission->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }
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

    private function authorizeEnglishAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminenglish'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }

    private function authorizeEnglishTeacherAccess(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminenglish', 'teacher-english'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
    }
}
