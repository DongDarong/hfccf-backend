<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Resources\Preschool\PreschoolReceiptResource;
use App\Models\PreschoolPayment;
use App\Models\PreschoolReceipt;
use App\Services\PreschoolBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolReceiptController extends PreschoolBillingController
{
    public function __construct(private readonly PreschoolBillingService $billing) {}

    public function store(Request $request, string $payment): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $paymentModel = PreschoolPayment::query()->with(['invoice', 'student', 'receipts'])->find($payment);
        if (! $paymentModel) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'reissue' => ['sometimes', 'boolean'],
        ]);

        try {
            $receipt = $this->billing->generateReceipt($paymentModel, $request->user(), (bool) ($validated['reissue'] ?? false));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'message' => 'Receipt generated successfully.',
            'data' => [
                'receipt' => PreschoolReceiptResource::make($receipt)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $receipt): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $receiptModel = PreschoolReceipt::query()->with(['payment.student', 'invoice', 'issuer', 'reissuedFrom'])->find($receipt);
        if (! $receiptModel) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool receipt retrieved successfully.',
            'data' => [
                'receipt' => PreschoolReceiptResource::make($receiptModel)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function print(Request $request, string $receipt): Response|JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $receiptModel = PreschoolReceipt::query()->with(['payment.student', 'invoice', 'issuer', 'reissuedFrom'])->find($receipt);
        if (! $receiptModel) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response($this->billing->renderReceiptPrintHtml($receiptModel), Response::HTTP_OK)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
