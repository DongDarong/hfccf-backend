<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\StoreSportEquipmentItemRequest;
use App\Http\Requests\Sport\UpdateSportEquipmentItemRequest;
use App\Http\Resources\Sport\SportEquipmentItemResource;
use App\Support\ApiResponse;
use App\Support\SportEquipmentItemStatus;
use App\Support\SportEquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SportEquipmentController extends SportController
{
    public function __construct(
        private readonly SportEquipmentService $equipmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:16'],
            'stock' => ['sometimes', 'nullable', 'string', 'in:available,low,out'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $paginator = $this->equipmentService->listItems($validated);

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

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $item = $this->equipmentService->findItemOrFail($id);
        } catch (\RuntimeException|\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment item not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse('Sport equipment retrieved successfully.', [
            'item' => SportEquipmentItemResource::make($item)->resolve($request),
        ]);
    }

    public function store(StoreSportEquipmentItemRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        $data = $request->validated();

        try {
            $item = $this->equipmentService->createItem($request->user(), $data);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Sport equipment created successfully.', [
            'item' => SportEquipmentItemResource::make($item)->resolve($request),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateSportEquipmentItemRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $item = $this->equipmentService->findItemOrFail($id);
        } catch (\RuntimeException|\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment item not found.', null, Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        try {
            $item = $this->equipmentService->updateItem($item, $request->user(), $data);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Sport equipment updated successfully.', [
            'item' => SportEquipmentItemResource::make($item)->resolve($request),
        ]);
    }
}
