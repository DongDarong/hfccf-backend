<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 100);

        $products = Product::query()
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            'Products retrieved successfully.',
            [
                'products' => ProductResource::collection($products->getCollection())->resolve($request),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ]
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return $this->successResponse(
            'Product created successfully.',
            (new ProductResource($product))->resolve($request),
            Response::HTTP_CREATED
        );
    }

    public function show(Request $request, string $product): JsonResponse
    {
        $product = Product::find($product);

        if (! $product) {
            return $this->notFoundResponse();
        }

        return $this->successResponse(
            'Product retrieved successfully.',
            (new ProductResource($product))->resolve($request)
        );
    }

    public function update(UpdateProductRequest $request, string $product): JsonResponse
    {
        $product = Product::find($product);

        if (! $product) {
            return $this->notFoundResponse();
        }

        $product->update($request->validated());

        return $this->successResponse(
            'Product updated successfully.',
            (new ProductResource($product->fresh()))->resolve($request)
        );
    }

    public function destroy(string $product): JsonResponse
    {
        $product = Product::find($product);

        if (! $product) {
            return $this->notFoundResponse();
        }

        $product->delete();

        return $this->successResponse(
            'Product deleted successfully.',
            null
        );
    }

    private function successResponse(string $message, mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Product not found.',
            'data' => null,
        ], Response::HTTP_NOT_FOUND);
    }
}
