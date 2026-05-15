<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function successResponse(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function errorResponse(string $message, mixed $data = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function paginatedResponse(
        string $message,
        LengthAwarePaginator $paginator,
        Request $request,
        string $resourceClass,
        string $itemKey = 'items',
    ): JsonResponse {
        $items = $resourceClass::collection($paginator->getCollection())->resolve($request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                $itemKey => $items,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ], 200);
    }
}
