<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportPlayingStyleResource;
use App\Models\SportPlayingStyle;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportPlayingStyleController extends SportController
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
            'status' => ['sometimes', 'nullable', 'string', 'in:active,inactive'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = SportPlayingStyle::query()->withCount('teams');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $sortColumn = match ($sortBy) {
            'name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
            default => 'created_at',
        };

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginatedResponse(
            'Sport playing styles retrieved successfully.',
            $paginator,
            $request,
            SportPlayingStyleResource::class,
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:sport_playing_styles,name'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $style = SportPlayingStyle::query()->create($data);

        return ApiResponse::successResponse(
            'Sport playing style created successfully.',
            [
                'playingStyle' => SportPlayingStyleResource::make($style->loadCount('teams'))->resolve($request),
            ],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $style = SportPlayingStyle::query()->withCount('teams')->find($id);
        if (! $style) {
            return ApiResponse::errorResponse('Playing style not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse(
            'Sport playing style retrieved successfully.',
            [
                'playingStyle' => SportPlayingStyleResource::make($style)->resolve($request),
            ],
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $style = SportPlayingStyle::query()->find($id);
        if (! $style) {
            return ApiResponse::errorResponse('Playing style not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', 'unique:sport_playing_styles,name,'.$id],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
        ]);

        $style->update($data);

        return ApiResponse::successResponse(
            'Sport playing style updated successfully.',
            [
                'playingStyle' => SportPlayingStyleResource::make($style->loadCount('teams'))->resolve($request),
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $style = SportPlayingStyle::query()->find($id);
        if (! $style) {
            return ApiResponse::errorResponse('Playing style not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ($style->teams()->exists()) {
            return ApiResponse::errorResponse(
                'Cannot delete playing style with assigned teams.',
                null,
                Response::HTTP_CONFLICT,
            );
        }

        $style->delete();

        return ApiResponse::successResponse('Sport playing style deleted successfully.');
    }
}
