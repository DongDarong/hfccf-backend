<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolPaymentRequest;
use App\Http\Requests\Preschool\UpdatePreschoolPaymentRequest;
use App\Http\Resources\Preschool\PreschoolPaymentResource;
use App\Models\PreschoolPayment;
use App\Services\PreschoolBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolPaymentController extends PreschoolBillingController
{
    public function __construct(private readonly PreschoolBillingService $billing) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $query = PreschoolPayment::query()->with(['student', 'preschoolClass', 'invoice', 'receipts']);
        $this->applyFilters($request, $query);

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request), ['*'], 'page', $this->page($request));

        return response()->json([
            'success' => true,
            'message' => 'Preschool payments retrieved successfully.',
            'data' => [
                'items' => PreschoolPaymentResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolPaymentRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $result = $this->billing->createPaymentWorkflow($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully.',
            'data' => [
                'invoice' => isset($result['invoice']) ? \App\Http\Resources\Preschool\PreschoolInvoiceResource::make($result['invoice'])->resolve($request) : null,
                'payment' => PreschoolPaymentResource::make($result['payment'])->resolve($request),
                'receipt' => ! empty($result['receipt'])
                    ? \App\Http\Resources\Preschool\PreschoolReceiptResource::make($result['receipt'])->resolve($request)
                    : null,
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payment = PreschoolPayment::query()->with(['student', 'preschoolClass', 'invoice', 'receipts'])->find($id);

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool payment retrieved successfully.',
            'data' => [
                'payment' => PreschoolPaymentResource::make($payment)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolPaymentRequest $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payment = PreschoolPayment::query()->with(['invoice'])->find($id);
        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $updated = $this->billing->updatePayment($payment, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool payment updated successfully.',
            'data' => [
                'payment' => PreschoolPaymentResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payment = PreschoolPayment::query()->find($id);

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $previousInvoiceId = $payment->invoice_id;
        $payment->delete();
        if ($previousInvoiceId) {
            $this->billing->syncPaymentInvoiceBalances($payment, $previousInvoiceId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool payment deleted successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function applyFilters(Request $request, Builder $query): void
    {
        $search = trim((string) $request->query('search', ''));
        $classId = trim((string) $request->query('class_id', ''));
        $studentId = trim((string) $request->query('student_id', ''));
        $status = trim((string) $request->query('payment_status', $request->query('status', '')));
        $method = trim((string) $request->query('payment_method', ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('payment_reference', 'like', $like)
                    ->orWhere('currency', 'like', $like)
                    ->orWhere('payment_status', 'like', $like)
                    ->orWhere('payment_method', 'like', $like)
                    ->orWhereHas('student', static function (Builder $studentQuery) use ($like): void {
                        $studentQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('student_code', 'like', $like);
                    })
                    ->orWhereHas('preschoolClass', static function (Builder $classQuery) use ($like): void {
                        $classQuery->where('code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        if ($classId !== '') {
            $query->where('class_id', $classId);
        }
        if ($studentId !== '') {
            $query->where('student_id', $studentId);
        }
        if ($status !== '') {
            $query->where('payment_status', $status);
        }
        if ($method !== '') {
            $query->where('payment_method', $method);
        }
    }

    private function nextPaymentReference(): string
    {
        $count = PreschoolPayment::withTrashed()->count() + 1;

        return 'PAY-'.now()->format('Ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

}
