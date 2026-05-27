<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolClassroomResourceRequest;
use App\Http\Requests\Preschool\UpdatePreschoolClassroomResourceRequest;
use App\Http\Resources\Preschool\PreschoolClassroomResourceResource;
use App\Models\PreschoolClassroomResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolClassroomResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:32'],
            'condition' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = trim((string) ($validated['search'] ?? ''));
        $category = trim((string) ($validated['category'] ?? ''));
        $condition = trim((string) ($validated['condition'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = PreschoolClassroomResource::query();

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('name', 'like', $like)
                    ->orWhere('notes', 'like', $like);
            });
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($condition !== '') {
            $query->where('condition', $condition);
        }

        $sortColumn = match ($sortBy) {
            'name' => 'name',
            'category' => 'category',
            'quantity' => 'quantity',
            'condition' => 'condition',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Classroom resources retrieved successfully.',
            'data' => [
                'items' => PreschoolClassroomResourceResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolClassroomResourceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $resource = PreschoolClassroomResource::query()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Classroom resource created successfully.',
            'data' => [
                'resource' => PreschoolClassroomResourceResource::make($resource)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $resource = PreschoolClassroomResource::query()->find($id);

        if (! $resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Classroom resource retrieved successfully.',
            'data' => [
                'resource' => PreschoolClassroomResourceResource::make($resource)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolClassroomResourceRequest $request, string $id): JsonResponse
    {
        $resource = PreschoolClassroomResource::query()->find($id);

        if (! $resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $resource->fill($request->validated());
        $resource->save();

        return response()->json([
            'success' => true,
            'message' => 'Classroom resource updated successfully.',
            'data' => [
                'resource' => PreschoolClassroomResourceResource::make($resource)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $resource = PreschoolClassroomResource::query()->find($id);

        if (! $resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $resource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Classroom resource deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function authorizePreschoolUser(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacherpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
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
