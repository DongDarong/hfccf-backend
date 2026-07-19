<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Resources\Sport\SportEquipmentAssignmentResource;
use App\Support\ApiResponse;
use App\Support\SportEquipmentAssignmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportEquipmentAssignmentController extends SportController
{
    public function __construct(
        private readonly SportEquipmentAssignmentService $assignmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = $this->filters($request);
        $paginator = $this->assignmentService->listAdminAssignments($validated);

        return $this->successList($request, $paginator, 'Sport equipment assignments retrieved successfully.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $assignment = $this->assignmentService->findAssignmentOrFail($id);
        } catch (ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment assignment not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse('Sport equipment assignment retrieved successfully.', [
            'assignment' => SportEquipmentAssignmentResource::make($assignment)->resolve($request),
        ]);
    }

    public function itemHistory(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = array_merge($this->filters($request), ['equipment_item_id' => $id]);
        $paginator = $this->assignmentService->listAdminAssignments($validated);

        return $this->successList($request, $paginator, 'Equipment assignment history retrieved successfully.');
    }

    public function teamHistory(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = array_merge($this->filters($request), ['team_id' => $id]);
        $paginator = $this->assignmentService->listAdminAssignments($validated);

        return $this->successList($request, $paginator, 'Team equipment assignment history retrieved successfully.');
    }

    public function coachIndex(Request $request): JsonResponse
    {
        if (! $request->user() || $request->user()->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $validated = $this->filters($request);
        $paginator = $this->assignmentService->listCoachAssignments($request->user(), $validated);

        return $this->successList($request, $paginator, 'Coach equipment assignments retrieved successfully.');
    }

    public function coachShow(Request $request, string $id): JsonResponse
    {
        if (! $request->user() || $request->user()->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $assignment = $this->assignmentService->findAssignmentOrFail($id);
        } catch (ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment assignment not found.', null, Response::HTTP_NOT_FOUND);
        }

        if (! $this->assignmentService->coachCanView($request->user(), $assignment)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        return ApiResponse::successResponse('Coach equipment assignment retrieved successfully.', [
            'assignment' => SportEquipmentAssignmentResource::make($assignment)->resolve($request),
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'in:assigned,returned'],
            'equipment_item_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_equipment_items,id'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'coach_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);
    }

    private function successList(Request $request, $paginator, string $message): JsonResponse
    {
        return ApiResponse::successResponse($message, [
            'items' => SportEquipmentAssignmentResource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }
}
