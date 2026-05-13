<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminUsersRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function index(ListAdminUsersRequest $request): JsonResponse
    {
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
                'permissions' => fn ($builder) => $builder->orderBy('permissions.code'),
            ])
            ->whereIn('role_code', User::ADMIN_ROLES);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%');
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
            'message' => 'Admin users retrieved successfully.',
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
}
