<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolInvoiceRequest;
use App\Http\Requests\Preschool\UpdatePreschoolInvoiceRequest;
use App\Http\Resources\Preschool\PreschoolInvoiceResource;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolStudent;
use App\Services\PreschoolBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PreschoolInvoiceController extends PreschoolBillingController
{
    public function __construct(private readonly PreschoolBillingService $billing) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $query = PreschoolInvoice::query()->with([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.invoice',
            'payments.receipts',
            'receipts.payment.student',
            'receipts.invoice',
        ]);
        $this->applyFilters($request, $query);

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request), ['*'], 'page', $this->page($request));

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoices retrieved successfully.',
            'data' => [
                'items' => PreschoolInvoiceResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolInvoiceRequest $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoice = $this->billing->createInvoice($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoice created successfully.',
            'data' => [
                'invoice' => PreschoolInvoiceResource::make($invoice)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $invoice): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()->with([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.invoice',
            'payments.receipts',
            'receipts.payment.student',
            'receipts.invoice',
        ])->find($invoice);

        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoice retrieved successfully.',
            'data' => [
                'invoice' => PreschoolInvoiceResource::make($invoiceModel)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolInvoiceRequest $request, string $invoice): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()->with(['items', 'payments.invoice', 'payments.receipts'])->find($invoice);
        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $updated = $this->billing->updateDraftInvoice($invoiceModel, $request->validated(), $request->user());
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoice updated successfully.',
            'data' => [
                'invoice' => PreschoolInvoiceResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function issue(Request $request, string $invoice): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()->find($invoice);
        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $updated = $this->billing->issueInvoice($invoiceModel, $request->user());
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoice issued successfully.',
            'data' => [
                'invoice' => PreschoolInvoiceResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function cancel(Request $request, string $invoice): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()->find($invoice);
        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $updated = $this->billing->cancelInvoice($invoiceModel, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoice cancelled successfully.',
            'data' => [
                'invoice' => PreschoolInvoiceResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $invoice): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()
            ->with(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments.invoice', 'payments.receipts', 'receipts.payment.student', 'receipts.invoice'])
            ->find($invoice);
        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $deleted = $this->billing->deleteDraftInvoice($invoiceModel, $request->user());
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoice deleted successfully.',
            'data' => [
                'invoice' => PreschoolInvoiceResource::make($deleted)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function overdue(Request $request, string $invoice): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()->find($invoice);
        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $updated = $this->billing->markOverdue($invoiceModel, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool invoice overdue status checked successfully.',
            'data' => [
                'invoice' => PreschoolInvoiceResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function studentInvoices(Request $request, string $student): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $studentModel = PreschoolStudent::query()->find($student);
        if (! $studentModel) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $invoices = PreschoolInvoice::query()
            ->with([
                'student',
                'preschoolClass',
                'academicYear',
                'term',
                'items',
                'payments.invoice',
                'payments.receipts',
                'receipts.payment.student',
                'receipts.invoice',
            ])
            ->where('student_id', $studentModel->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Preschool student invoices retrieved successfully.',
            'data' => [
                'items' => PreschoolInvoiceResource::collection($invoices)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function studentPaymentSummary(Request $request, string $student): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $studentModel = PreschoolStudent::query()->find($student);
        if (! $studentModel) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $summary = $this->billing->studentSummary($studentModel);
        $summary['recentInvoices'] = PreschoolInvoiceResource::collection($summary['recentInvoices'] ?? [])->resolve($request);
        $summary['recentReceipts'] = \App\Http\Resources\Preschool\PreschoolReceiptResource::collection($summary['recentReceipts'] ?? [])->resolve($request);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student payment summary retrieved successfully.',
            'data' => $summary,
        ], Response::HTTP_OK);
    }

    public function print(Request $request, string $invoice): Response|JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()->with([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.invoice',
            'payments.receipts',
            'receipts.payment.student',
            'receipts.invoice',
        ])->find($invoice);
        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response($this->billing->renderInvoicePrintHtml($invoiceModel), Response::HTTP_OK)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function download(Request $request, string $invoice): Response|JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $invoiceModel = PreschoolInvoice::query()->with([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.receipts',
            'receipts.payment.student',
            'receipts.invoice',
        ])->find($invoice);
        if (! $invoiceModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'format' => ['sometimes', 'nullable', Rule::in(['pdf', 'xlsx'])],
        ]);

        try {
            $export = $this->billing->exportInvoice($invoiceModel, $validated['format'] ?? 'pdf');
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Invoice PDF rendering is temporarily unavailable.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response($export['content'], Response::HTTP_OK)
            ->header('Content-Type', $export['mimeType'])
            ->header('Content-Disposition', 'attachment; filename="'.$export['filename'].'"');
    }

    private function applyFilters(Request $request, Builder $query): void
    {
        $search = trim((string) $request->query('search', ''));
        $classId = trim((string) $request->query('class_id', ''));
        $studentId = trim((string) $request->query('student_id', ''));
        $status = trim((string) $request->query('status', $request->query('invoice_status', '')));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('invoice_number', 'like', $like)
                    ->orWhere('status', 'like', $like)
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
            $query->where('status', $status);
        }
    }
}
