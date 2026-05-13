<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminUsersRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function index(ListAdminUsersRequest $request): JsonResponse
    {
        if ($forbiddenResponse = $this->authorizeViewAllUsers($request->user())) {
            return $forbiddenResponse;
        }

        $validated = $request->validated();

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $role = trim((string) ($validated['role'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query = User::query()
            ->with([
                'role',
                'permissions' => fn ($builder) => $builder->orderBy('permissions.code'),
            ]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('role_code', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereHas('role', function ($roleQuery) use ($like): void {
                        $roleQuery->where('code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });

                if (DB::getDriverName() === 'sqlite') {
                    $builder->orWhereRaw("(first_name || ' ' || last_name) like ?", [$like]);
                } else {
                    $builder->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]);
                }
            });
        }

        if ($role !== '') {
            $query->where('role_code', $role);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $sortColumn = match ($sortBy) {
            'first_name' => 'first_name',
            'email' => 'email',
            'role' => 'role_code',
            'status' => 'status',
            default => 'created_at',
        };

        $summaryQuery = clone $query;

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        $statusCounts = [
            'active' => (clone $summaryQuery)->where('status', 'active')->count(),
            'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
            'inactive' => (clone $summaryQuery)->where('status', 'inactive')->count(),
            'suspended' => (clone $summaryQuery)->where('status', 'suspended')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'System users retrieved successfully.',
            'data' => [
                'items' => UserResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'summary' => [
                    'total' => $summaryQuery->count(),
                    'active' => $statusCounts['active'],
                    'pending' => $statusCounts['pending'],
                    'alerts' => $statusCounts['inactive'] + $statusCounts['suspended'],
                    'status_counts' => $statusCounts,
                ],
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeViewAllUsers(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->role_code === 'superadmin') {
            return null;
        }

        $permissionCodes = $user->relationLoaded('permissions')
            ? $user->permissions->pluck('code')->all()
            : $user->permissions()->pluck('permissions.code')->all();

        if (in_array('all:*', $permissionCodes, true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
