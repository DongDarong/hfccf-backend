<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportEquipmentRequest;
use App\Http\Resources\Sport\SportEquipmentItemResource;
use App\Http\Resources\Sport\SportEquipmentRequestResource;
use App\Models\SportEquipmentItem;
use App\Models\SportTeam;
use App\Support\ApiResponse;
use App\Support\SportCoachAssignmentService;
use App\Support\SportEquipmentRequestService;
use App\Support\SportEquipmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportCoachEquipmentRequestController extends SportController
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportEquipmentService $equipmentService,
        private readonly SportEquipmentRequestService $requestService,
    ) {}

    public function equipment(Request $request): JsonResponse
    {
        if (! $request->user() || $request->user()->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'stock' => ['sometimes', 'nullable', 'string', 'in:available,low,out'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $paginator = $this->equipmentService->listRequestableItems($validated);

        return ApiResponse::successResponse('Sport equipment retrieved successfully.', [
            'items' => SportEquipmentItemResource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
            'summary' => $this->equipmentService->summary(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        if (! $request->user() || $request->user()->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:16'],
            'team_id' => ['sometimes', 'nullable', 'integer'],
            'equipment_item_id' => ['sometimes', 'nullable', 'integer'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $paginator = $this->requestService->coachRequests($request->user(), $validated);

        return ApiResponse::successResponse('Sport equipment requests retrieved successfully.', [
            'items' => SportEquipmentRequestResource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
            'summary' => $this->requestService->coachSummary($request->user()),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (! $request->user() || $request->user()->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $item = $this->requestService->findRequestOrFail($id);
        } catch (ModelNotFoundException|\RuntimeException) {
            return ApiResponse::errorResponse('Equipment request not found.', null, Response::HTTP_NOT_FOUND);
        }

        if ((string) $item->coach_user_id !== (string) $request->user()->id) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        return ApiResponse::successResponse('Sport equipment request retrieved successfully.', [
            'request' => SportEquipmentRequestResource::make($item)->resolve($request),
        ]);
    }

    public function store(StoreSportEquipmentRequest $request): JsonResponse
    {
        if (! $request->user() || $request->user()->role_code !== 'coach') {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();
        $team = SportTeam::query()->find($data['team_id']);
        $item = SportEquipmentItem::query()->find($data['equipment_item_id']);

        if (! $team || ! $item) {
            return ApiResponse::errorResponse('Equipment item or team could not be found.', null, Response::HTTP_NOT_FOUND);
        }

        if (! $this->assignmentService->coachCanManageTeam($request->user(), $team)) {
            return ApiResponse::errorResponse('Forbidden.', null, Response::HTTP_FORBIDDEN);
        }

        try {
            $created = $this->requestService->createCoachRequest($request->user(), $team, $item, $data);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Sport equipment request created successfully.', [
            'request' => SportEquipmentRequestResource::make($created)->resolve($request),
        ], Response::HTTP_CREATED);
    }
}
