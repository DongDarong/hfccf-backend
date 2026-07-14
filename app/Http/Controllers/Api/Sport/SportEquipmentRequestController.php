<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Requests\Sport\ApproveSportEquipmentRequest;
use App\Http\Requests\Sport\IssueSportEquipmentRequest;
use App\Http\Requests\Sport\RejectSportEquipmentRequest;
use App\Http\Requests\Sport\ReturnSportEquipmentRequest;
use App\Http\Resources\Sport\SportEquipmentRequestResource;
use App\Support\ApiResponse;
use App\Support\SportEquipmentRequestService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportEquipmentRequestController extends SportController
{
    public function __construct(
        private readonly SportEquipmentRequestService $requestService,
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
            'status' => ['sometimes', 'nullable', 'string', 'max:16'],
            'coach_user_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'team_id' => ['sometimes', 'nullable', 'integer'],
            'equipment_item_id' => ['sometimes', 'nullable', 'integer'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $paginator = $this->requestService->adminRequests($validated);

        return ApiResponse::successResponse('Sport equipment requests retrieved successfully.', [
            'items' => SportEquipmentRequestResource::collection($paginator->getCollection())->resolve($request),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
            'summary' => $this->requestService->adminSummary(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $item = $this->requestService->findRequestOrFail($id);
        } catch (ModelNotFoundException|\RuntimeException) {
            return ApiResponse::errorResponse('Equipment request not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::successResponse('Sport equipment request retrieved successfully.', [
            'request' => SportEquipmentRequestResource::make($item)->resolve($request),
        ]);
    }

    public function approve(ApproveSportEquipmentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $item = $this->requestService->findRequestOrFail($id);
            $updated = $this->requestService->approveRequest($item, $request->user(), (int) $request->validated('approved_quantity'), $request->validated('admin_note') ?? null);
        } catch (ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment request not found.', null, Response::HTTP_NOT_FOUND);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Sport equipment request approved successfully.', [
            'request' => SportEquipmentRequestResource::make($updated)->resolve($request),
        ]);
    }

    public function reject(RejectSportEquipmentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $item = $this->requestService->findRequestOrFail($id);
            $updated = $this->requestService->rejectRequest($item, $request->user(), (string) $request->validated('rejected_reason'), $request->validated('admin_note') ?? null);
        } catch (ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment request not found.', null, Response::HTTP_NOT_FOUND);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Sport equipment request rejected successfully.', [
            'request' => SportEquipmentRequestResource::make($updated)->resolve($request),
        ]);
    }

    public function issue(IssueSportEquipmentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $item = $this->requestService->findRequestOrFail($id);
            $updated = $this->requestService->issueRequest($item, $request->user(), (int) $request->validated('issued_quantity'), $request->validated('admin_note') ?? null);
        } catch (ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment request not found.', null, Response::HTTP_NOT_FOUND);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Sport equipment request issued successfully.', [
            'request' => SportEquipmentRequestResource::make($updated)->resolve($request),
        ]);
    }

    public function returnRequest(ReturnSportEquipmentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) {
            return $response;
        }

        try {
            $item = $this->requestService->findRequestOrFail($id);
            $updated = $this->requestService->returnRequest(
                $item,
                $request->user(),
                (int) $request->validated('returned_quantity'),
                (int) $request->validated('damaged_quantity'),
                (int) $request->validated('missing_quantity'),
                $request->validated('admin_note') ?? null,
            );
        } catch (ModelNotFoundException) {
            return ApiResponse::errorResponse('Equipment request not found.', null, Response::HTTP_NOT_FOUND);
        } catch (\RuntimeException $exception) {
            return ApiResponse::errorResponse($exception->getMessage(), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::successResponse('Sport equipment request returned successfully.', [
            'request' => SportEquipmentRequestResource::make($updated)->resolve($request),
        ]);
    }
}
