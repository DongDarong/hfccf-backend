<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportTrainingSessionRequest;
use App\Http\Requests\Sport\UpdateSportTrainingSessionRequest;
use App\Http\Resources\Sport\SportTrainingSessionResource;
use App\Models\SportTrainingSession;
use App\Support\ApiResponse;
use App\Support\SportTrainingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportTrainingSessionController extends SportController
{
    public function __construct(private readonly SportTrainingSessionService $service) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportCoach($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'team_id' => ['sometimes', 'integer', 'exists:sport_teams,id'],
            'status' => ['sometimes', 'nullable', 'string'],
            'training_type' => ['sometimes', 'nullable', 'string'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'sort_by' => ['sometimes', 'string'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ]);

        $query = $this->service->visibleQuery($request->user())
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhere('session_code', 'like', '%'.$search.'%')
                        ->orWhere('venue', 'like', '%'.$search.'%')
                        ->orWhereHas('team', fn ($teamQuery) => $teamQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(isset($validated['team_id']), fn ($query) => $query->where('team_id', $validated['team_id']))
            ->when(isset($validated['status']) && $validated['status'] !== '', fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['training_type']) && $validated['training_type'] !== '', fn ($query) => $query->where('training_type', $validated['training_type']))
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('starts_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('starts_at', '<=', $date));

        $allowedSorts = ['title', 'starts_at', 'ends_at', 'status', 'training_type', 'intensity', 'created_at'];
        $sortBy = in_array($validated['sort_by'] ?? 'starts_at', $allowedSorts, true) ? ($validated['sort_by'] ?? 'starts_at') : 'starts_at';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        $sessions = $query->orderBy($sortBy, $sortDirection)
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return ApiResponse::paginatedResponse('Training sessions retrieved successfully.', $sessions, $request, SportTrainingSessionResource::class);
    }

    public function store(StoreSportTrainingSessionRequest $request): JsonResponse
    {
        $session = $this->service->create($request->validated(), $request->user());

        return ApiResponse::successResponse('Training session created successfully.', (new SportTrainingSessionResource($session))->resolve(), Response::HTTP_CREATED);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        if ($response = $this->authorizeSportCoach($request->user())) {
            return $response;
        }

        $session = $this->service->findVisible($request->user(), $id);
        if (! $session) {
            return ApiResponse::errorResponse('Training session not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse('Training session retrieved successfully.', (new SportTrainingSessionResource($session))->resolve());
    }

    public function update(UpdateSportTrainingSessionRequest $request, int|string $id): JsonResponse
    {
        $session = SportTrainingSession::query()->find($id);
        if (! $session) {
            return ApiResponse::errorResponse('Training session not found.', null, Response::HTTP_NOT_FOUND);
        }

        $session = $this->service->update($session, $request->validated(), $request->user());

        return ApiResponse::successResponse('Training session updated successfully.', (new SportTrainingSessionResource($session))->resolve());
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $session = SportTrainingSession::query()->find($id);
        if (! $session) {
            return ApiResponse::errorResponse('Training session not found.', null, Response::HTTP_NOT_FOUND);
        }

        $this->service->delete($session);

        return ApiResponse::successResponse('Training session deleted successfully.');
    }
}
