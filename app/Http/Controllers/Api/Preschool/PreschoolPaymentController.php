<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolPaymentRequest;
use App\Http\Requests\Preschool\UpdatePreschoolPaymentRequest;
use App\Http\Resources\Preschool\PreschoolPaymentResource;
use App\Models\PreschoolPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $query = PreschoolPayment::query()->with(['student', 'preschoolClass']);
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

        $data = $request->validated();
        $payment = PreschoolPayment::query()->create([
            'student_id' => $data['student_id'],
            'class_id' => $data['class_id'],
            'payment_reference' => $data['payment_reference'] ?? $this->nextPaymentReference(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
            'paid_at' => $data['paid_at'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        $payment->load(['student', 'preschoolClass']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool payment created successfully.',
            'data' => [
                'payment' => PreschoolPaymentResource::make($payment)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payment = PreschoolPayment::query()->with(['student', 'preschoolClass'])->find($id);

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

        $payment = PreschoolPayment::query()->find($id);
        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();
        foreach (['student_id', 'class_id', 'payment_reference', 'amount', 'currency', 'payment_method', 'payment_status', 'paid_at', 'due_date', 'note'] as $field) {
            if (array_key_exists($field, $data)) {
                $payment->{$field} = $data[$field];
            }
        }

        $payment->save();
        $payment->load(['student', 'preschoolClass']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool payment updated successfully.',
            'data' => [
                'payment' => PreschoolPaymentResource::make($payment)->resolve($request),
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

        $payment->delete();

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

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    private function page(Request $request): int
    {
        return max((int) $request->query('page', 1), 1);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 10), 1), 100);
    }

    private function paginationShape($paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
