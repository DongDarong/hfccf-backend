<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PreschoolClass;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolInvoiceItem;
use App\Models\PreschoolPayment;
use App\Models\PreschoolReceipt;
use App\Models\PreschoolStudent;
use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as SpreadsheetXlsxWriter;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class PreschoolBillingService
{
    public function createInvoice(array $data, ?User $actor = null): PreschoolInvoice
    {
        return DB::transaction(function () use ($data, $actor): PreschoolInvoice {
            $invoice = PreschoolInvoice::query()->create([
                'student_id' => $data['student_id'],
                'class_id' => $data['class_id'],
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'term_id' => $data['term_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? $this->nextInvoiceNumber(),
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => 0,
                'discount_amount' => $this->decimal($data['discount_amount'] ?? 0),
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance_due' => 0,
                'status' => 'draft',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->syncInvoiceItems($invoice, $data['items'] ?? []);
            $this->refreshInvoiceTotals($invoice->fresh(['items']));

            return $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items']);
        });
    }

    public function updateDraftInvoice(PreschoolInvoice $invoice, array $data, ?User $actor = null): PreschoolInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $actor): PreschoolInvoice {
            $invoice->refresh();
            $this->ensureEditable($invoice);

            foreach (['student_id', 'class_id', 'academic_year_id', 'term_id', 'invoice_number', 'issue_date', 'due_date'] as $field) {
                if (array_key_exists($field, $data)) {
                    $invoice->{$field} = $data[$field];
                }
            }

            if (array_key_exists('discount_amount', $data)) {
                $invoice->discount_amount = $this->decimal($data['discount_amount']);
            }

            $invoice->updated_by = $actor?->id;
            $invoice->save();

            if (array_key_exists('items', $data)) {
                $this->syncInvoiceItems($invoice, $data['items'] ?? []);
            }

            $this->refreshInvoiceTotals($invoice);

            return $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']);
        });
    }

    public function issueInvoice(PreschoolInvoice $invoice, ?User $actor = null): PreschoolInvoice
    {
        return DB::transaction(function () use ($invoice, $actor): PreschoolInvoice {
            $invoice->refresh(['items', 'payments']);
            if ($invoice->status === 'cancelled') {
                throw new \RuntimeException('Cancelled invoices cannot be issued.');
            }

            if ($invoice->status === 'draft' && blank($invoice->issue_date)) {
                $invoice->issue_date = now()->toDateString();
            }

            $invoice->status = 'issued';
            $invoice->updated_by = $actor?->id;
            $invoice->save();

            $this->refreshInvoiceTotals($invoice);

            return $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']);
        });
    }

    public function cancelInvoice(PreschoolInvoice $invoice, ?User $actor = null): PreschoolInvoice
    {
        return DB::transaction(function () use ($invoice, $actor): PreschoolInvoice {
            $invoice->refresh();
            $invoice->status = 'cancelled';
            $invoice->updated_by = $actor?->id;
            $invoice->save();

            return $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']);
        });
    }

    public function deleteDraftInvoice(PreschoolInvoice $invoice, ?User $actor = null): PreschoolInvoice
    {
        return DB::transaction(function () use ($invoice, $actor): PreschoolInvoice {
            $invoice->refresh(['items', 'payments', 'receipts']);

            if ($invoice->status !== 'draft') {
                throw new \RuntimeException('Only draft invoices can be deleted. Cancel issued invoices instead.');
            }

            if ($invoice->payments()->exists() || $invoice->receipts()->exists()) {
                throw new \RuntimeException('Invoices with payments or receipts cannot be deleted.');
            }

            $invoice->updated_by = $actor?->id;
            $invoice->save();
            $invoice->items()->delete();
            $invoice->delete();

            return PreschoolInvoice::withTrashed()
                ->with(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts'])
                ->findOrFail($invoice->id);
        });
    }

    public function markOverdue(PreschoolInvoice $invoice, ?User $actor = null): PreschoolInvoice
    {
        return DB::transaction(function () use ($invoice, $actor): PreschoolInvoice {
            $invoice->refresh(['items', 'payments']);
            if ($invoice->status === 'cancelled' || $invoice->balance_due <= 0) {
                return $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']);
            }

            if ($invoice->due_date && $invoice->due_date->isPast()) {
                $invoice->status = 'overdue';
                $invoice->updated_by = $actor?->id;
                $invoice->save();
            }

            return $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']);
        });
    }

    public function refreshInvoiceTotals(PreschoolInvoice $invoice): PreschoolInvoice
    {
        $invoice->loadMissing(['items', 'payments']);

        $subtotal = $invoice->items->sum(static fn (PreschoolInvoiceItem $item): float => (float) $item->amount);
        $discount = $this->decimal($invoice->discount_amount ?? 0);
        $total = max($subtotal - $discount, 0);
        $paid = $invoice->payments
            ->filter(static fn (PreschoolPayment $payment): bool => $payment->deleted_at === null && $payment->payment_status === 'paid')
            ->sum(static fn (PreschoolPayment $payment): float => (float) $payment->amount);
        $balance = max($total - $paid, 0);

        $invoice->subtotal = $subtotal;
        $invoice->total_amount = $total;
        $invoice->paid_amount = $paid;
        $invoice->balance_due = $balance;

        if ($invoice->status !== 'cancelled' && $invoice->status !== 'draft') {
            $invoice->status = $this->resolveInvoiceStatus($invoice);
        }

        $invoice->save();

        return $invoice;
    }

    public function syncPaymentInvoiceBalances(PreschoolPayment $payment, ?int $previousInvoiceId = null): void
    {
        $invoiceIds = collect([$previousInvoiceId, $payment->invoice_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($invoiceIds as $invoiceId) {
            $invoice = PreschoolInvoice::query()->find($invoiceId);
            if ($invoice) {
                $this->refreshInvoiceTotals($invoice);
            }
        }
    }

    public function generateReceipt(PreschoolPayment $payment, ?User $actor = null, bool $forceReissue = false): PreschoolReceipt
    {
        return DB::transaction(function () use ($payment, $actor, $forceReissue): PreschoolReceipt {
            $payment->refresh(['invoice', 'receipts']);
            if ($payment->invoice && $payment->invoice->status === 'cancelled') {
                throw new \RuntimeException('Cancelled invoices cannot generate receipts.');
            }

            $existing = $payment->receipts()->latest('issued_at')->first();
            if ($existing && ! $forceReissue) {
                return $existing->load(['payment', 'invoice']);
            }

            if ($payment->payment_status !== 'paid') {
                $payment->payment_status = 'paid';
                if (blank($payment->paid_at)) {
                    $payment->paid_at = now();
                }
                $payment->save();
            }

            if ($payment->invoice_id) {
                $this->refreshInvoiceTotals($payment->invoice()->with('items', 'payments')->first());
            }

            $receipt = PreschoolReceipt::query()->create([
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'reissued_from_receipt_id' => $existing?->id,
                'receipt_number' => $this->nextReceiptNumber(),
                'issued_at' => now(),
                'issued_by' => $actor?->id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'notes' => $payment->note,
            ]);

            return $receipt->fresh(['payment', 'invoice']);
        });
    }

    /**
     * Create a payment using the canonical accounting flow.
     *
     * Existing invoice mode records a payment against an already-issued invoice.
     * Quick invoice mode creates the invoice, its first item, the payment, and
     * the receipt in a single transaction.
     *
     * @return array{invoice:?PreschoolInvoice,payment:PreschoolPayment,receipt:PreschoolReceipt}
     */
    public function createPaymentWorkflow(array $data, ?User $actor = null): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $paymentStatus = strtolower(trim((string) ($data['payment_status'] ?? 'paid'))) ?: 'paid';
            $mode = $this->resolvePaymentMode($data['mode'] ?? null, $data);
            $student = $this->resolveActiveStudent((int) ($data['student_id'] ?? 0));
            $class = null;

            if (! empty($data['class_id'])) {
                $class = $this->resolveActiveClass((int) $data['class_id']);
            }

            $amount = $this->decimal($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount must be greater than zero.'],
                ]);
            }

            $paymentMethod = trim((string) ($data['payment_method'] ?? ''));
            if ($paymentMethod === '') {
                throw ValidationException::withMessages([
                    'payment_method' => ['Payment method is required.'],
                ]);
            }

            $paymentReference = $this->resolvePaymentReference($data['payment_reference'] ?? null);
            $paidAt = $this->resolveDateTime($data['paid_at'] ?? null) ?? now();
            $note = isset($data['note']) ? trim((string) $data['note']) : null;
            $currency = strtoupper(trim((string) ($data['currency'] ?? 'USD'))) ?: 'USD';

            if ($mode === 'quick_invoice' && ! empty($data['invoice_id'])) {
                $mode = 'existing_invoice';
            }

            if ($mode === 'quick_invoice' && empty($data['invoice_id']) && $paymentStatus !== 'paid') {
                $payment = $this->createPaymentRecord([
                    'student_id' => $student->id,
                    'class_id' => $class?->id ?? $student->classes()->wherePivot('status', 'active')->value('preschool_classes.id'),
                    'payment_reference' => $paymentReference,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'paid_at' => $data['paid_at'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'note' => $note,
                ], $actor);

                return [
                    'invoice' => null,
                    'payment' => $payment->fresh(['student', 'preschoolClass', 'invoice', 'receipts']),
                    'receipt' => null,
                ];
            }

            if ($mode === 'existing_invoice') {
                $invoice = $this->resolvePayableInvoice((int) ($data['invoice_id'] ?? 0), $student->id);

                if ($class && (int) $class->id !== (int) $invoice->class_id) {
                    throw ValidationException::withMessages([
                        'class_id' => ['The selected class does not match the selected invoice.'],
                    ]);
                }

                if ($amount > max((float) $invoice->balance_due, 0)) {
                    throw ValidationException::withMessages([
                        'amount' => ['Amount exceeds the available balance.'],
                    ]);
                }

                $payment = $this->createPaymentRecord([
                    'student_id' => $student->id,
                    'class_id' => $invoice->class_id,
                    'invoice_id' => $invoice->id,
                    'payment_reference' => $paymentReference,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'paid_at' => $paidAt,
                    'due_date' => $data['due_date'] ?? $invoice->due_date?->toDateString(),
                    'note' => $note,
                ], $actor);

                $receipt = $this->generateReceipt($payment, $actor);

                return [
                    'invoice' => $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']),
                    'payment' => $payment->fresh(['student', 'preschoolClass', 'invoice', 'receipts']),
                    'receipt' => $receipt->fresh(['payment', 'invoice']),
                ];
            }

            if ($class === null) {
                throw ValidationException::withMessages([
                    'class_id' => ['Class is required for quick invoice payments.'],
                ]);
            }

            if (! $student->classes()
                ->whereKey($class->id)
                ->wherePivot('status', 'active')
                ->exists()) {
                throw ValidationException::withMessages([
                    'class_id' => ['The selected class does not belong to the selected student.'],
                ]);
            }

            $description = trim((string) ($data['description'] ?? ''));
            if ($description === '') {
                throw ValidationException::withMessages([
                    'description' => ['Invoice description is required for quick invoice payments.'],
                ]);
            }

            $issueDate = $this->resolveDate($data['issue_date'] ?? $paidAt)?->toDateString();
            $issueDate = $issueDate ?: now()->toDateString();
            $dueDate = $this->resolveDate($data['due_date'] ?? null);
            if (! $dueDate) {
                throw ValidationException::withMessages([
                    'due_date' => ['Due date is required for quick invoice payments.'],
                ]);
            }

            if ($dueDate->lt(Carbon::parse($issueDate)->startOfDay())) {
                throw ValidationException::withMessages([
                    'due_date' => ['Due date must be on or after the invoice date.'],
                ]);
            }

            $invoice = PreschoolInvoice::query()->create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'term_id' => $data['term_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? $this->nextInvoiceNumber(),
                'issue_date' => $issueDate,
                'due_date' => $dueDate->toDateString(),
                'subtotal' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance_due' => 0,
                'status' => 'issued',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $invoice->items()->create([
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'sort_order' => 1,
            ]);

            $this->refreshInvoiceTotals($invoice->fresh(['items', 'payments']));

            $payment = $this->createPaymentRecord([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'invoice_id' => $invoice->id,
                'payment_reference' => $paymentReference,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'paid_at' => $paidAt,
                'due_date' => $dueDate->toDateString(),
                'note' => $note,
            ], $actor);

            $receipt = $this->generateReceipt($payment, $actor);

            return [
                'invoice' => $invoice->fresh(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']),
                'payment' => $payment->fresh(['student', 'preschoolClass', 'invoice', 'receipts']),
                'receipt' => $receipt->fresh(['payment', 'invoice']),
            ];
        });
    }

    /**
     * Update a payment while keeping invoice balances authoritative.
     */
    public function updatePayment(PreschoolPayment $payment, array $data, ?User $actor = null): PreschoolPayment
    {
        return DB::transaction(function () use ($payment, $data, $actor): PreschoolPayment {
            $payment->refresh();
            $previousInvoiceId = $payment->invoice_id;
            $previousAmount = (float) $payment->amount;
            $targetInvoiceId = array_key_exists('invoice_id', $data) && $data['invoice_id'] !== null
                ? (int) $data['invoice_id']
                : $previousInvoiceId;
            $targetInvoice = null;

            if ($targetInvoiceId) {
                $targetInvoice = PreschoolInvoice::query()->with(['items', 'payments', 'receipts'])->lockForUpdate()->find($targetInvoiceId);
                if (! $targetInvoice) {
                    throw ValidationException::withMessages([
                        'invoice_id' => ['The selected invoice does not exist.'],
                    ]);
                }

                if ($targetInvoice->student_id !== (int) ($data['student_id'] ?? $payment->student_id)) {
                    throw ValidationException::withMessages([
                        'student_id' => ['The selected invoice does not belong to the selected student.'],
                    ]);
                }

                $this->assertInvoiceIsPayable($targetInvoice, $payment->id);
            }

            $amount = array_key_exists('amount', $data) ? $this->decimal($data['amount']) : $previousAmount;
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount must be greater than zero.'],
                ]);
            }

            if ($targetInvoice) {
                $available = $this->decimal($targetInvoice->balance_due) + ($targetInvoice->id === $previousInvoiceId ? $previousAmount : 0);
                if ($amount > $available) {
                    throw ValidationException::withMessages([
                        'amount' => ['Amount exceeds the available balance.'],
                    ]);
                }
            }

            foreach (['student_id', 'class_id', 'invoice_id', 'payment_reference', 'amount', 'currency', 'payment_method', 'payment_status', 'paid_at', 'due_date', 'note'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payment->{$field} = $data[$field];
                }
            }

            if (array_key_exists('payment_status', $data) && $payment->payment_status === 'paid' && blank($payment->paid_at)) {
                $payment->paid_at = now();
            }

            if ($targetInvoice) {
                $payment->student_id = $targetInvoice->student_id;
                $payment->class_id = $targetInvoice->class_id;
            }

            $payment->updated_at = now();
            $payment->save();
            $payment->load(['student', 'preschoolClass', 'invoice', 'receipts']);
            $this->syncPaymentInvoiceBalances($payment, $previousInvoiceId);

            return $payment->fresh(['student', 'preschoolClass', 'invoice', 'receipts']);
        });
    }

    /**
     * Generate a downloadable invoice export in the requested format.
     *
     * @return array{filename:string,mimeType:string,content:string}
     */
    public function exportInvoice(PreschoolInvoice $invoice, string $format = 'pdf'): array
    {
        $invoice->loadMissing(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments.receipts', 'receipts.payment.student']);
        $format = strtolower(trim($format));
        $filename = $this->invoiceExportFilename($invoice, $format);

        return match ($format) {
            'xlsx', 'excel' => [
                'filename' => $filename,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'content' => $this->buildInvoiceXlsxSpreadsheet($invoice),
            ],
            default => [
                'filename' => $filename,
                'mimeType' => 'application/pdf',
                'content' => $this->buildInvoicePdf($invoice),
            ],
        };
    }

    private function createPaymentRecord(array $data, ?User $actor = null): PreschoolPayment
    {
        try {
            $paymentStatus = strtolower(trim((string) ($data['payment_status'] ?? 'paid'))) ?: 'paid';
            $payment = PreschoolPayment::query()->create([
                'student_id' => $data['student_id'],
                'class_id' => $data['class_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'term_id' => $data['term_id'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? $this->resolvePaymentReference(null),
                'amount' => $this->decimal($data['amount'] ?? 0),
                'currency' => strtoupper(trim((string) ($data['currency'] ?? 'USD'))) ?: 'USD',
                'payment_method' => $data['payment_method'],
                'payment_status' => $paymentStatus,
                'paid_at' => array_key_exists('paid_at', $data)
                    ? $data['paid_at']
                    : ($paymentStatus === 'paid' ? now() : null),
                'due_date' => $data['due_date'] ?? null,
                'note' => $data['note'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $actor?->id,
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'payment_reference' => ['Duplicate payment reference.'],
                ]);
            }

            throw $exception;
        }

        return $payment->fresh(['student', 'preschoolClass', 'invoice', 'receipts']);
    }

    private function resolvePaymentMode(mixed $mode, array $data = []): string
    {
        $resolved = strtolower(trim((string) ($mode ?? '')));

        if ($resolved === '') {
            $resolved = ! empty($data['invoice_id']) ? 'existing_invoice' : 'quick_invoice';
        }

        if (! in_array($resolved, ['existing_invoice', 'quick_invoice'], true)) {
            throw ValidationException::withMessages([
                'mode' => ['Invalid payment source selected.'],
            ]);
        }

        return $resolved;
    }

    private function resolveActiveStudent(int $studentId): PreschoolStudent
    {
        $student = PreschoolStudent::query()->find($studentId);

        if (! $student) {
            throw ValidationException::withMessages([
                'student_id' => ['The selected student does not exist.'],
            ]);
        }

        if (strtolower(trim((string) $student->status)) !== 'active') {
            throw ValidationException::withMessages([
                'student_id' => ['Inactive or archived students cannot be billed.'],
            ]);
        }

        return $student;
    }

    private function resolveActiveClass(int $classId): PreschoolClass
    {
        $class = PreschoolClass::query()->find($classId);

        if (! $class) {
            throw ValidationException::withMessages([
                'class_id' => ['The selected class does not exist.'],
            ]);
        }

        if (strtolower(trim((string) $class->status)) !== 'active') {
            throw ValidationException::withMessages([
                'class_id' => ['Inactive or archived classes cannot be billed.'],
            ]);
        }

        return $class;
    }

    private function resolvePayableInvoice(int $invoiceId, int $studentId): PreschoolInvoice
    {
        $invoice = PreschoolInvoice::query()
            ->with(['items', 'payments', 'receipts', 'student', 'preschoolClass'])
            ->lockForUpdate()
            ->find($invoiceId);

        if (! $invoice) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The selected invoice does not exist.'],
            ]);
        }

        if ((int) $invoice->student_id !== $studentId) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The selected invoice does not belong to the selected student.'],
            ]);
        }

        $this->assertInvoiceIsPayable($invoice);

        return $invoice;
    }

    private function assertInvoiceIsPayable(PreschoolInvoice $invoice, ?int $currentPaymentId = null): void
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice_id' => ['Cancelled invoices cannot receive payments.'],
            ]);
        }

        if ($invoice->status === 'draft') {
            throw ValidationException::withMessages([
                'invoice_id' => ['Draft invoices must be issued before they can receive payments.'],
            ]);
        }

        $paidAmount = (float) $invoice->payments
            ->filter(static function (PreschoolPayment $payment) use ($currentPaymentId): bool {
                if ($payment->deleted_at !== null) {
                    return false;
                }

                if ($currentPaymentId !== null && (int) $payment->id === $currentPaymentId) {
                    return false;
                }

                return $payment->payment_status === 'paid';
            })
            ->sum(static fn (PreschoolPayment $payment): float => (float) $payment->amount);
        $balance = max((float) $invoice->total_amount - $paidAmount, 0);

        if ($balance <= 0) {
            throw ValidationException::withMessages([
                'invoice_id' => ['The selected invoice is already fully paid.'],
            ]);
        }
    }

    private function resolvePaymentReference(mixed $reference): string
    {
        $normalized = trim((string) ($reference ?? ''));
        if ($normalized !== '') {
            return $normalized;
        }

        return 'PAY-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
    }

    private function resolveDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        return Carbon::parse($value);
    }

    private function resolveDateTime(mixed $value): ?Carbon
    {
        return $this->resolveDate($value);
    }

    private function invoiceExportFilename(PreschoolInvoice $invoice, string $format): string
    {
        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $invoice->invoice_number) ?: ('invoice-'.$invoice->id);
        $safeInvoiceNumber = trim($safeInvoiceNumber, '-');
        $extension = in_array($format, ['xlsx', 'excel'], true) ? 'xlsx' : 'pdf';

        return sprintf('preschool-invoice-%s.%s', $safeInvoiceNumber, $extension);
    }

    private function buildInvoicePdf(PreschoolInvoice $invoice): string
    {
        $invoice->loadMissing([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.receipts',
            'receipts',
        ]);

        return $this->renderPdfHtmlWithBrowser($this->renderInvoicePdfHtml($invoice));
    }

    public function renderInvoicePdfHtml(PreschoolInvoice $invoice): string
    {
        $invoice->loadMissing([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.receipts',
            'receipts',
        ]);

        $labels = $this->invoiceExportLabels();
        $studentName = trim(($invoice->student?->first_name ?? '').' '.($invoice->student?->last_name ?? '')) ?: '-';
        $latinName = trim((string) ($invoice->student?->latin_name ?? '')) ?: null;
        $className = $invoice->preschoolClass?->name ?? '-';
        $payments = $invoice->payments->map(function (PreschoolPayment $payment): array {
            return [
                'date' => $this->formatDateValue($payment->paid_at),
                'reference' => $payment->payment_reference ?: '-',
                'method' => $this->friendlyPaymentMethodLabel($payment->payment_method),
                'amount' => $this->formatMoney((float) $payment->amount),
                'receipt' => $payment->receipts->first()?->receipt_number ?? '-',
            ];
        })->values();
        $receipts = $invoice->receipts->map(function (PreschoolReceipt $receipt): array {
            return [
                'number' => $receipt->receipt_number ?: '-',
                'generated_at' => $this->formatDateTimeValue($receipt->issued_at),
                'amount' => $this->formatMoney((float) $receipt->amount),
            ];
        })->values();
        $organization = $this->invoicePdfOrganization();

        return view('pdf.preschool-invoice', [
            'labels' => $labels,
            'appName' => $this->appName(),
            'programName' => $labels['program_name'],
            'organization' => $organization,
            'organizationLines' => $organization['details'],
            'fontRegularPath' => $this->pdfFontFileUri('NotoSansKhmer-Regular.ttf'),
            'fontBoldPath' => $this->pdfFontFileUri('NotoSansKhmer-Bold.ttf'),
            'invoice' => $invoice,
            'studentName' => $studentName,
            'latinName' => $latinName,
            'className' => $className,
            'academicYearName' => $invoice->academicYear?->name ?? '-',
            'termName' => $invoice->term?->name ?? '-',
            'statusLabel' => $this->humanizeInvoiceStatus($invoice->status),
            'createdAt' => $this->formatDateTimeValue($invoice->created_at),
            'issueDate' => $this->formatDateValue($invoice->issue_date),
            'dueDate' => $this->formatDateValue($invoice->due_date),
            'subtotal' => $this->formatMoney((float) $invoice->subtotal),
            'discount' => $this->formatMoney((float) $invoice->discount_amount),
            'total' => $this->formatMoney((float) $invoice->total_amount),
            'paid' => $this->formatMoney((float) $invoice->paid_amount),
            'balance' => $this->formatMoney((float) $invoice->balance_due),
            'generatedAt' => $this->formatDateTimeValue(now()),
            'items' => $invoice->items,
            'payments' => $payments,
            'receipts' => $receipts,
        ])->render();
    }

    private function buildInvoiceXlsxSpreadsheet(PreschoolInvoice $invoice): string
    {
        $invoice->loadMissing([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
        ]);

        $organization = $this->invoicePdfOrganization();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Invoice');
        $sheet->setShowGridLines(false);

        $spreadsheet->getProperties()
            ->setCreator($this->appName())
            ->setLastModifiedBy($this->appName())
            ->setTitle('Preschool Invoice')
            ->setSubject('Preschool Invoice')
            ->setDescription('HFCCF preschool invoice workbook');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Noto Sans Khmer')->setSize(10);
        $sheet->getPageMargins()->setTop(0.35);
        $sheet->getPageMargins()->setRight(0.25);
        $sheet->getPageMargins()->setBottom(0.35);
        $sheet->getPageMargins()->setLeft(0.25);
        $sheet->getPageMargins()->setHeader(0.2);
        $sheet->getPageMargins()->setFooter(0.2);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setVerticalCentered(false)
            ->setRowsToRepeatAtTopByStartAndEnd(13, 13);

        $sheet->freezePane('A14');

        foreach ([
            'A' => 6,
            'B' => 18,
            'C' => 12,
            'D' => 12,
            'E' => 12,
            'F' => 12,
            'G' => 10,
            'H' => 13,
            'I' => 14,
            'J' => 14,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        foreach (range(1, 4) as $row) {
            $sheet->getRowDimension($row)->setRowHeight($row === 1 ? 24 : 20);
        }
        $sheet->getRowDimension(5)->setRowHeight(6);
        $sheet->getRowDimension(6)->setRowHeight(20);
        foreach (range(7, 10) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(22);
        }
        $sheet->getRowDimension(11)->setRowHeight(4);
        $sheet->getRowDimension(12)->setRowHeight(20);
        $sheet->getRowDimension(13)->setRowHeight(24);

        $invoiceNumber = (string) $invoice->invoice_number;
        $studentId = (string) ($invoice->student?->public_id ?? $invoice->student?->student_code ?? $invoice->student?->id ?? '-');
        $studentName = trim(($invoice->student?->first_name ?? '').' '.($invoice->student?->last_name ?? '')) ?: '-';
        $latinName = trim((string) ($invoice->student?->latin_name ?? '')) ?: '-';
        $className = $invoice->preschoolClass?->name ?? '-';
        $statusLabel = $this->humanizeInvoiceStatus($invoice->status);
        $generatedAt = now()->format('Y-m-d H:i');
        $logoPath = $organization['logo_path'] ?? null;
        if (! is_string($logoPath) || $logoPath === '' || ! File::exists($logoPath)) {
            $logoPath = $this->officialOrganizationLogoPath();
        }

        $sheet->mergeCells('A1:B4');
        $sheet->mergeCells('C1:D1');
        $sheet->mergeCells('C2:D2');
        $sheet->mergeCells('C3:D3');
        $sheet->mergeCells('E1:G4');
        $sheet->mergeCells('H1:J1');
        $sheet->mergeCells('H2:J2');
        $sheet->mergeCells('H3:J3');
        $sheet->mergeCells('H4:J4');
        $sheet->mergeCells('A6:E6');
        $sheet->mergeCells('F6:J6');
        $sheet->mergeCells('A7:B7');
        $sheet->mergeCells('C7:E7');
        $sheet->mergeCells('F7:G7');
        $sheet->mergeCells('H7:J7');
        $sheet->mergeCells('A8:B8');
        $sheet->mergeCells('C8:E8');
        $sheet->mergeCells('F8:G8');
        $sheet->mergeCells('H8:J8');
        $sheet->mergeCells('A9:B9');
        $sheet->mergeCells('C9:E9');
        $sheet->mergeCells('F9:G9');
        $sheet->mergeCells('H9:J9');
        $sheet->mergeCells('A10:B10');
        $sheet->mergeCells('C10:E10');
        $sheet->mergeCells('F10:G10');
        $sheet->mergeCells('H10:J10');
        $sheet->mergeCells('A12:J12');
        $sheet->mergeCells('B13:F13');

        $sheet->setCellValueExplicit('C1', (string) $organization['kh_name'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C2', (string) $organization['en_name'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C3', 'Preschool Program', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E1', "វិក្កយបត្រ\nINVOICE", DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H1', "Invoice Number\n{$invoiceNumber}", DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H2', 'Invoice Date'."\n".$this->formatDateValue($invoice->issue_date), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H3', 'Due Date'."\n".$this->formatDateValue($invoice->due_date), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H4', "Status\n{$statusLabel}", DataType::TYPE_STRING);

        $sheet->setCellValueExplicit('A6', 'Student Information', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F6', 'Invoice Information', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A7', 'Student Name', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C7', $studentName, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F7', 'Academic Year', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H7', (string) ($invoice->academicYear?->name ?? '-'), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A8', 'Latin Name', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C8', $latinName, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F8', 'Term', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H8', (string) ($invoice->term?->name ?? '-'), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A9', 'Student ID', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C9', $studentId, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F9', 'Payment Status', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H9', $statusLabel, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A10', 'Class', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C10', $className, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F10', 'Created Date', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H10', $this->formatDateTimeValue($invoice->created_at), DataType::TYPE_STRING);

        $sheet->setCellValueExplicit('A12', 'Invoice Items', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A13', 'No.', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B13', 'Description', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('G13', 'Quantity', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H13', 'Unit Price', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('I13', 'Amount', DataType::TYPE_STRING);

        $itemRow = 14;
        foreach ($invoice->items as $index => $item) {
            $description = trim((string) $item->description) !== '' ? (string) $item->description : '-';
            $sheet->mergeCells('B'.$itemRow.':F'.$itemRow);
            $sheet->setCellValueExplicit('A'.$itemRow, $index + 1, DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('B'.$itemRow, $description, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G'.$itemRow, (float) $item->quantity, DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('H'.$itemRow, (float) $item->unit_price, DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit('I'.$itemRow, (float) $item->amount, DataType::TYPE_NUMERIC);
            $sheet->getRowDimension($itemRow)->setRowHeight(22);
            ++$itemRow;
        }

        $totalsStart = $itemRow + 1;
        $sheet->getRowDimension($itemRow)->setRowHeight(6);
        $summaryRows = [
            ['label' => 'Subtotal', 'value' => (float) $invoice->subtotal],
            ['label' => 'Discount', 'value' => (float) $invoice->discount_amount],
            ['label' => 'Total Amount', 'value' => (float) $invoice->total_amount, 'highlight' => true],
            ['label' => 'Paid Amount', 'value' => (float) $invoice->paid_amount],
            ['label' => 'Balance Due', 'value' => (float) $invoice->balance_due, 'highlight' => true],
        ];

        foreach ($summaryRows as $offset => $summaryRow) {
            $row = $totalsStart + $offset;
            $sheet->mergeCells('F'.$row.':H'.$row);
            $sheet->mergeCells('I'.$row.':J'.$row);
            $sheet->setCellValueExplicit('F'.$row, $summaryRow['label'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('I'.$row, (float) $summaryRow['value'], DataType::TYPE_NUMERIC);
        }

        $footerStart = $totalsStart + count($summaryRows) + 2;
        $contactLines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), $organization['details'] ?? []),
            static fn (string $line): bool => $line !== '',
        ));
        $footerContacts = $contactLines !== [] ? implode('   •   ', $contactLines) : $this->appName();
        $footerNotice = "This invoice was generated electronically by the HFCCF System and does not require a signature.\nវិក្កយបត្រនេះត្រូវបានបង្កើតដោយប្រព័ន្ធ HFCCF និងមិនត្រូវការហត្ថលេខា។";

        $sheet->mergeCells('A'.$footerStart.':J'.$footerStart);
        $sheet->setCellValueExplicit('A'.$footerStart, $footerContacts, DataType::TYPE_STRING);
        $sheet->mergeCells('A'.($footerStart + 1).':J'.($footerStart + 1));
        $sheet->setCellValueExplicit('A'.($footerStart + 1), $footerNotice, DataType::TYPE_STRING);
        $sheet->mergeCells('A'.($footerStart + 2).':E'.($footerStart + 2));
        $sheet->mergeCells('F'.($footerStart + 2).':J'.($footerStart + 2));
        $sheet->setCellValueExplicit('A'.($footerStart + 2), 'Invoice Number: '.$invoiceNumber, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F'.($footerStart + 2), 'Generated On: '.$generatedAt, DataType::TYPE_STRING);

        $sheet->getStyle('A1:J4')->applyFromArray([
            'font' => [
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFD7DEE7'],
                ],
            ],
        ]);
        $sheet->getStyle('C1:C3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Noto Sans Khmer',
                'color' => ['rgb' => 'FF0F172A'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('E1:G4')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 18,
                'name' => 'Noto Sans Khmer',
                'color' => ['rgb' => 'FF0F172A'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('H1:J4')->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFD7DEE7'],
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFF8FBFF'],
            ],
        ]);
        $sheet->getStyle('H4:J4')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => match (strtolower(trim($invoice->status))) {
                    'paid' => 'FFDCFCE7',
                    'partial', 'pending' => 'FFFDE68A',
                    'overdue' => 'FFFECACA',
                    'cancelled' => 'FFE2E8F0',
                    default => 'FFEAF2FF',
                }],
            ],
        ]);
        $sheet->getStyle('A6:J6')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Noto Sans Khmer',
                'color' => ['rgb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FF1D4F91'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FF1D4F91'],
                ],
            ],
        ]);
        $sheet->getStyle('A7:J10')->applyFromArray([
            'font' => [
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFD7DEE7'],
                ],
            ],
        ]);
        $sheet->getStyle('A7:B10')->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFEAF2FF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('F7:G10')->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFEAF2FF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('C7:E10')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('H7:J10')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('A12:J12')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Noto Sans Khmer',
                'color' => ['rgb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FF1D4F91'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A13:J'.max($itemRow - 1, 13))->applyFromArray([
            'font' => [
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFD7DEE7'],
                ],
            ],
        ]);
        $sheet->getStyle('A13:J13')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'name' => 'Noto Sans Khmer',
                'color' => ['rgb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FF1D4F91'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('A14:A'.max($itemRow - 1, 14))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $sheet->getStyle('G14:G'.max($itemRow - 1, 14))->getNumberFormat()->setFormatCode('0.##');
        $sheet->getStyle('H14:I'.max($itemRow - 1, 14))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A14:A'.max($itemRow - 1, 14))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G14:G'.max($itemRow - 1, 14))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H14:I'.max($itemRow - 1, 14))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('B14:F'.max($itemRow - 1, 14))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $summaryEnd = $totalsStart + count($summaryRows) - 1;
        $sheet->getStyle('F'.$totalsStart.':J'.$summaryEnd)->applyFromArray([
            'font' => [
                'name' => 'Noto Sans Khmer',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFD7DEE7'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('F'.$totalsStart.':H'.$summaryEnd)->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFF8FBFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('I'.$totalsStart.':J'.$summaryEnd)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getStyle('I'.$totalsStart.':J'.$summaryEnd)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F'.($totalsStart + 2).':J'.($totalsStart + 2))->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFEAF2FF'],
            ],
        ]);

        $sheet->getStyle('A'.$footerStart.':J'.($footerStart + 2))->applyFromArray([
            'font' => [
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFD7DEE7'],
                ],
            ],
        ]);
        $sheet->getStyle('A'.$footerStart.':J'.$footerStart)->applyFromArray([
            'font' => [
                'italic' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FFF8FBFF'],
            ],
        ]);
        $sheet->getStyle('A'.($footerStart + 1).':J'.($footerStart + 1))->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A'.($footerStart + 2).':E'.($footerStart + 2))->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);
        $sheet->getStyle('F'.($footerStart + 2).':J'.($footerStart + 2))->applyFromArray([
            'font' => [
                'bold' => true,
                'name' => 'Noto Sans Khmer',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
        ]);

        if (is_string($logoPath) && $logoPath !== '' && File::exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('HFCCF Logo');
            $drawing->setDescription('HFCCF official logo');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            $drawing->setResizeProportional(true);
            $drawing->setHeight(76);
            $drawing->setWorksheet($sheet);
        }

        $lastRow = $footerStart + 2;
        $sheet->getPageSetup()->setPrintArea('A1:J'.$lastRow);

        $tempPath = tempnam(sys_get_temp_dir(), 'hfccf_invoice_');
        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create a temporary file for invoice XLSX export.');
        }

        $xlsxPath = $tempPath.'.xlsx';
        if (! @rename($tempPath, $xlsxPath)) {
            $xlsxPath = $tempPath;
        }

        try {
            $writer = new SpreadsheetXlsxWriter($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($xlsxPath);

            return (string) File::get($xlsxPath);
        } finally {
            File::delete($xlsxPath);
            if ($xlsxPath !== $tempPath) {
                File::delete($tempPath);
            }
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function createPdfFromLines(array $lines): string
    {
        $pages = [];
        $wrappedLines = [];
        foreach ($lines as $line) {
            foreach ($this->wrapPdfLine($line) as $wrappedLine) {
                $wrappedLines[] = $wrappedLine;
            }
        }

        foreach (array_chunk($wrappedLines, 42) as $chunk) {
            $pages[] = $this->buildPdfPage($chunk);
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $objectOffset = 3;
        $fontObjectNumber = 3 + (count($pages) * 2);

        foreach ($pages as $index => $page) {
            $kids[] = ($objectOffset + $index * 2).' 0 R';
        }

        $objects[] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';

        foreach ($pages as $index => $page) {
            $contentObject = (string) ($objectOffset + $index * 2 + 1);
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObjectNumber} 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[] = '<< /Length '.strlen($page).' >>'."\nstream\n{$page}\nendstream";
        }

        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $buffer = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($buffer);
            $buffer .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($buffer);
        $buffer .= "xref\n";
        $buffer .= '0 '.(count($objects) + 1)."\n";
        $buffer .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $buffer .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $buffer .= "trailer\n";
        $buffer .= '<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n";
        $buffer .= "startxref\n";
        $buffer .= $xref."\n";
        $buffer .= "%%EOF";

        return $buffer;
    }

    private function buildPdfPage(array $lines): string
    {
        $leading = 16;
        $content = "BT\n/F1 11 Tf\n{$leading} TL\n50 790 Td\n";
        $first = true;

        foreach ($lines as $line) {
            $escaped = $this->escapePdfText((string) $line);
            if ($first) {
                $content .= "({$escaped}) Tj\n";
                $first = false;
                continue;
            }

            $content .= "T*\n({$escaped}) Tj\n";
        }

        return $content."ET";
    }

    private function appendPdfSection(array &$lines, string $heading, array $entries, bool $omitLeadingBlank = false): void
    {
        if (! $omitLeadingBlank && ! empty($lines)) {
            $lines[] = '';
        }

        $lines[] = mb_strtoupper($heading, 'UTF-8');
        foreach ($entries as $entry) {
            $lines[] = (string) $entry;
        }
    }

    /**
     * @return array<int, string>
     */
    private function wrapPdfLine(string $line, int $width = 92): array
    {
        if ($line === '') {
            return [''];
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($line));
        if ($normalized === null || $normalized === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $normalized) ?: [];
        $wrapped = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate, 'UTF-8') <= $width) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $wrapped[] = $current;
                $current = $word;
                continue;
            }

            while (mb_strlen($word, 'UTF-8') > $width) {
                $wrapped[] = mb_substr($word, 0, $width, 'UTF-8');
                $word = mb_substr($word, $width, null, 'UTF-8');
            }

            $current = $word;
        }

        if ($current !== '') {
            $wrapped[] = $current;
        }

        return $wrapped === [] ? [''] : $wrapped;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function columnLetter(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $column = intdiv($column - 1, 26);
        }

        return $letters;
    }

    private function appName(): string
    {
        return (string) config('app.name', 'HFCCF');
    }

    private function invoicePdfOrganization(): array
    {
        $organization = Organization::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        $khName = trim((string) ($organization?->name_kh ?? ''));
        $enName = trim((string) ($organization?->name ?? ''));

        if ($khName === '') {
            $khName = 'អង្គការ មូលនិធិក្តីសង្ឃឹមនៃកុមារកម្ពុជា';
        }

        if ($enName === '') {
            $enName = 'Hope For Cambodian Children Fund (HFCCF)';
        }

        $details = array_values(array_filter([
            trim((string) ($organization?->address ?? '')),
            trim((string) ($organization?->phone ?? '')),
            trim((string) ($organization?->email ?? '')),
            $this->organizationWebsiteLine(),
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        return [
            'kh_name' => $khName,
            'en_name' => $enName,
            'details' => $details,
            'logo_data_uri' => $this->organizationLogoDataUri($organization?->logo),
            'logo_path' => $this->resolveOrganizationLogoPath($organization?->logo),
        ];
    }

    private function friendlyPaymentMethodLabel(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'khqr' => 'ABA KHQR',
            'aba', 'aba_khqr' => 'ABA KHQR',
            'wing' => 'Wing',
            'card', 'credit_card', 'debit_card' => 'Card',
            default => $normalized !== '' ? Str::headline(str_replace(['_', '-'], ' ', $normalized)) : '-',
        };
    }

    private function organizationWebsiteLine(): string
    {
        $appUrl = trim((string) config('app.url', ''));

        if ($appUrl === '' || str_contains($appUrl, 'hfccf-backend.test')) {
            return '';
        }

        return $appUrl;
    }

    private function organizationLogoDataUri(?string $logoPath): ?string
    {
        $resolvedPath = $this->resolveOrganizationLogoPath($logoPath)
            ?? $this->officialOrganizationLogoPath();

        if ($resolvedPath === null || ! File::exists($resolvedPath)) {
            return null;
        }

        $mime = File::mimeType($resolvedPath) ?: 'image/png';
        $content = File::get($resolvedPath);

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }

    private function resolveOrganizationLogoPath(?string $logoPath): ?string
    {
        $path = trim((string) $logoPath);

        if ($path === '') {
            return null;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $candidates = [
            $normalized,
            public_path($normalized),
            storage_path('app/public'.DIRECTORY_SEPARATOR.ltrim($normalized, DIRECTORY_SEPARATOR)),
            storage_path('app'.DIRECTORY_SEPARATOR.ltrim($normalized, DIRECTORY_SEPARATOR)),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function officialOrganizationLogoPath(): ?string
    {
        $path = public_path('images'.DIRECTORY_SEPARATOR.'hfccf-logo.png');

        return File::exists($path) ? $path : null;
    }

    private function invoiceExportLabels(): array
    {
        $isKh = str_starts_with(strtolower((string) app()->getLocale()), 'kh');

        if ($isKh) {
            return [
                'sheet_name' => 'វិក្កយបត្រ',
                'title' => 'វិក្កយបត្រ',
                'summary_heading' => 'ព័ត៌មានវិក្កយបត្រ',
                'items_heading' => 'ធាតុវិក្កយបត្រ',
                'items' => 'ធាតុវិក្កយបត្រ',
                'payments_heading' => 'ប្រវត្តិបង់ប្រាក់',
                'payments' => 'ប្រវត្តិបង់ប្រាក់',
                'student' => 'សិស្ស',
                'class' => 'ថ្នាក់',
                'issue_date' => 'ថ្ងៃចេញ',
                'due_date' => 'ថ្ងៃកំណត់',
                'status' => 'ស្ថានភាព',
                'subtotal' => 'សរុបមុនបញ្ចុះតម្លៃ',
                'discount' => 'បញ្ចុះតម្លៃ',
                'total' => 'ទឹកប្រាក់សរុប',
                'paid' => 'បានបង់',
                'balance' => 'សមតុល្យនៅសល់',
                'invoice_number' => 'លេខវិក្កយបត្រ',
                'description' => 'ការពិពណ៌នា',
                'quantity' => 'បរិមាណ',
                'unit_price' => 'តម្លៃឯកតា',
                'amount' => 'ទឹកប្រាក់',
                'payment_date' => 'ថ្ងៃបង់ប្រាក់',
                'payment_method' => 'វិធីបង់ប្រាក់',
                'payment_reference' => 'លេខយោងទូទាត់',
                'paid_amount' => 'ចំនួនបានបង់',
                'receipt_number' => 'លេខបង្កាន់ដៃ',
                'receipts' => 'បង្កាន់ដៃ',
                'receipts_heading' => 'បង្កាន់ដៃ',
                'summary' => 'សង្ខេប',
                'program_name' => 'កម្មវិធីមត្តេយ្យ',
                'bill_to' => 'វិក្កយបត្រទៅ',
                'invoice_information' => 'ព័ត៌មានវិក្កយបត្រ',
                'academic_year' => 'ឆ្នាំសិក្សា',
                'term' => 'វគ្គសិក្សា',
                'created_date' => 'ថ្ងៃបង្កើត',
                'latin_name' => 'ឈ្មោះឡាតាំង',
                'student_name' => 'ឈ្មោះសិស្ស',
                'item_no' => 'ល.រ',
                'payment_status' => 'ស្ថានភាពបង់ប្រាក់',
                'generated_date' => 'ថ្ងៃបង្កើត',
                'empty_payments' => 'មិនទាន់មានប្រវត្តិបង់ប្រាក់ទេ។',
                'empty_receipts' => 'មិនទាន់មានបង្កាន់ដៃទេ។',
                'footer_note' => 'ឯកសារនេះត្រូវបានបង្កើតពីប្រព័ន្ធគ្រប់គ្រងវិក្កយបត្រមត្តេយ្យ។',
            ];
        }

        return [
            'sheet_name' => 'Invoice',
            'title' => 'Invoice',
            'summary_heading' => 'Invoice Summary',
            'items_heading' => 'Invoice Items',
            'items' => 'Invoice Items',
            'payments_heading' => 'Payment History',
            'payments' => 'Payment History',
            'student' => 'Student',
            'class' => 'Class',
            'issue_date' => 'Invoice Date',
            'due_date' => 'Due Date',
            'status' => 'Status',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'total' => 'Total Amount',
            'paid' => 'Paid Amount',
            'balance' => 'Balance Due',
            'invoice_number' => 'Invoice Number',
            'description' => 'Description',
            'quantity' => 'Quantity',
            'unit_price' => 'Unit Price',
            'amount' => 'Amount',
            'payment_date' => 'Payment Date',
            'payment_method' => 'Payment Method',
            'payment_reference' => 'Payment Reference',
            'paid_amount' => 'Paid Amount',
            'receipt_number' => 'Receipt Number',
            'receipts' => 'Receipts',
            'receipts_heading' => 'Receipts',
            'summary' => 'Summary',
            'program_name' => 'Preschool Program',
            'bill_to' => 'Bill To',
            'invoice_information' => 'Invoice Information',
            'academic_year' => 'Academic Year',
            'term' => 'Term',
            'created_date' => 'Created Date',
            'latin_name' => 'Latin Name',
            'student_name' => 'Student Name',
            'item_no' => 'No.',
            'payment_status' => 'Payment Status',
            'generated_date' => 'Generated Date',
            'empty_payments' => 'No payment history recorded.',
            'empty_receipts' => 'No receipts generated.',
            'footer_note' => 'This invoice was generated by the Preschool billing system.',
        ];
    }

    private function formatDateValue(mixed $value): string
    {
        $date = $this->resolveDate($value);

        return $date ? $date->toDateString() : '-';
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    private function formatDateTimeValue(mixed $value): string
    {
        $date = $this->resolveDateTime($value);

        return $date ? $date->format('Y-m-d H:i') : '-';
    }

    private function formatNumber(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function humanizeInvoiceStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        $labels = [
            'draft' => 'Draft',
            'issued' => 'Issued',
            'partial' => 'Partially Paid',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ];

        if (str_starts_with(strtolower((string) app()->getLocale()), 'kh')) {
            $labels = [
                'draft' => 'ព្រាង',
                'issued' => 'បានចេញ',
                'partial' => 'បង់មួយផ្នែក',
                'paid' => 'បានបង់រួច',
                'overdue' => 'យឺតពេល',
                'cancelled' => 'បានលុប',
            ];
        }

        return $labels[$normalized] ?? $normalized;
    }

    public function studentSummary(PreschoolStudent $student): array
    {
        $invoices = PreschoolInvoice::query()
            ->with(['student', 'preschoolClass', 'items', 'payments', 'receipts'])
            ->where('student_id', $student->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->get();

        $receipts = PreschoolReceipt::query()
            ->with(['payment', 'invoice'])
            ->whereHas('payment', static fn ($query) => $query->where('student_id', $student->id))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
        $receiptCount = PreschoolReceipt::query()
            ->whereHas('payment', static fn ($query) => $query->where('student_id', $student->id))
            ->count();

        $totalBilled = (float) $invoices->sum('total_amount');
        $totalPaid = (float) $invoices->sum('paid_amount');
        $outstanding = max($totalBilled - $totalPaid, 0);

        return [
            'summary' => [
                'invoiceCount' => $invoices->count(),
                'receiptCount' => $receiptCount,
                'totalBilled' => round($totalBilled, 2),
                'totalPaid' => round($totalPaid, 2),
                'outstandingBalance' => round($outstanding, 2),
                'overdueCount' => $invoices->where('status', 'overdue')->count(),
                'paidCount' => $invoices->where('status', 'paid')->count(),
            ],
            'recentInvoices' => $invoices->take(5)->values(),
            'recentReceipts' => $receipts->values(),
        ];
    }

    public function renderInvoicePrintHtml(PreschoolInvoice $invoice): string
    {
        return $this->renderInvoicePdfHtml($invoice);
    }

    public function renderReceiptPrintHtml(PreschoolReceipt $receipt): string
    {
        $receipt->loadMissing(['payment.student', 'invoice.preschoolClass']);
        $studentName = trim(($receipt->payment?->student?->first_name ?? '').' '.($receipt->payment?->student?->last_name ?? ''));

        return $this->renderDocument('Preschool Receipt', [
            'title' => 'Preschool Receipt',
            'subtitle' => 'Receipt '.$receipt->receipt_number,
            'metadata' => [
                ['label' => 'Student', 'value' => $studentName],
                ['label' => 'Invoice', 'value' => $receipt->invoice?->invoice_number ?? '-'],
                ['label' => 'Issued At', 'value' => $receipt->issued_at?->toDateTimeString() ?? '-'],
                ['label' => 'Payment Method', 'value' => $receipt->payment_method ?? '-'],
            ],
            'rows' => '',
            'summary' => [
                ['label' => 'Amount', 'value' => number_format((float) $receipt->amount, 2)],
                ['label' => 'Notes', 'value' => $receipt->notes ?: '-'],
            ],
        ]);
    }

    public function nextInvoiceNumber(): string
    {
        $count = PreschoolInvoice::withTrashed()->count() + 1;

        return 'INV-'.now()->format('Ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function nextReceiptNumber(): string
    {
        $count = PreschoolReceipt::withTrashed()->count() + 1;

        return 'RCT-'.now()->format('Ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function syncInvoiceItems(PreschoolInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $quantity = $this->decimal($item['quantity'] ?? 1);
            $unitPrice = $this->decimal($item['unit_price'] ?? 0);

            $invoice->items()->create([
                'description' => (string) ($item['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => round($quantity * $unitPrice, 2),
                'sort_order' => (int) ($item['sort_order'] ?? $index + 1),
            ]);
        }
    }

    private function ensureEditable(PreschoolInvoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new \RuntimeException('Only draft invoices can be edited.');
        }
    }

    private function resolveInvoiceStatus(PreschoolInvoice $invoice): string
    {
        if ($invoice->status === 'cancelled') {
            return 'cancelled';
        }

        if ($invoice->status === 'draft') {
            return 'draft';
        }

        if ((float) $invoice->balance_due <= 0) {
            return 'paid';
        }

        if ($invoice->due_date && $invoice->due_date->isPast()) {
            return 'overdue';
        }

        if ((float) $invoice->paid_amount > 0) {
            return 'partial';
        }

        return 'issued';
    }

    private function decimal(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function renderDocument(string $heading, array $data): string
    {
        $metadataRows = collect($data['metadata'] ?? [])->map(static function (array $row): string {
            return '<li><strong>'.e($row['label']).':</strong> '.e((string) $row['value']).'</li>';
        })->implode('');

        $summaryRows = collect($data['summary'] ?? [])->map(static function (array $row): string {
            return '<li><strong>'.e($row['label']).':</strong> '.e((string) $row['value']).'</li>';
        })->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($heading).'</title>'
            .'<style>body{font-family:Arial,sans-serif;margin:32px;color:#111827}h1,h2{margin:0 0 12px}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #d1d5db;padding:8px;text-align:left}ul{list-style:none;padding:0;margin:0 0 12px}li{margin:4px 0}.summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:16px}</style>'
            .'</head><body>'
            .'<h1>'.e($data['title'] ?? $heading).'</h1>'
            .'<h2>'.e($data['subtitle'] ?? '').'</h2>'
            .'<ul>'.$metadataRows.'</ul>'
            .'<table><thead><tr><th>Description</th><th style="text-align:right">Quantity</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Amount</th></tr></thead><tbody>'.($data['rows'] ?? '').'</tbody></table>'
            .'<ul class="summary">'.$summaryRows.'</ul>'
            .'</body></html>';
    }

    private function renderPdfHtmlWithBrowser(string $html): string
    {
        $browserBinary = $this->resolvePdfBrowserBinary();
        $directory = storage_path('app/tmp/preschool-invoices');

        File::ensureDirectoryExists($directory);

        $token = (string) Str::uuid();
        $htmlPath = $directory.DIRECTORY_SEPARATOR.$token.'.html';
        $pdfPath = $directory.DIRECTORY_SEPARATOR.$token.'.pdf';
        $profilePath = $directory.DIRECTORY_SEPARATOR.$token.'-profile';

        try {
            File::put($htmlPath, $html);

            File::ensureDirectoryExists($profilePath);

            $result = $this->runPdfBrowserProcess($browserBinary, $htmlPath, $pdfPath, $profilePath);

            if ($result->failed() || ! File::exists($pdfPath)) {
                Log::error('Preschool invoice PDF rendering failed.', [
                    'binary' => basename($browserBinary),
                    'browser_selected' => basename($browserBinary),
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                    'stdout' => $result->output(),
                    'html_exists' => File::exists($htmlPath),
                    'pdf_exists' => File::exists($pdfPath),
                    'profile_exists' => File::exists($profilePath),
                ]);

                throw new \RuntimeException('Invoice PDF rendering is temporarily unavailable.');
            }

            return (string) File::get($pdfPath);
        } catch (Throwable $exception) {
            Log::error('Failed to render invoice PDF via browser engine.', [
                'binary' => basename($browserBinary),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'html_exists' => File::exists($htmlPath),
                'pdf_exists' => File::exists($pdfPath),
                'profile_exists' => File::exists($profilePath),
            ]);

            throw $exception;
        } finally {
            File::delete([$htmlPath, $pdfPath]);
            File::deleteDirectory($profilePath);
        }
    }

    protected function runPdfBrowserProcess(string $browserBinary, string $htmlPath, string $pdfPath, string $profilePath)
    {
        return Process::timeout(120)->run([
                $browserBinary,
                '--headless=new',
                '--disable-gpu',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--user-data-dir='.$profilePath,
                '--print-to-pdf='.$pdfPath,
                $this->filePathToUri($htmlPath),
            ]);
    }

    protected function resolvePdfBrowserBinary(): string
    {
        $configured = $this->normalizeConfiguredBrowserBinary(config('services.preschool_pdf.browser_binary'));
        if (is_string($configured) && $configured !== '') {
            if (File::exists($configured)) {
                return $configured;
            }

            Log::warning('Configured Preschool PDF browser binary was not found.', [
                'configured_binary' => $configured,
            ]);

            throw new \RuntimeException('Invoice PDF rendering is temporarily unavailable.');
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        Log::error('No supported browser binary found for Preschool invoice PDF rendering.');

        throw new \RuntimeException('Invoice PDF rendering is temporarily unavailable.');
    }

    private function normalizeConfiguredBrowserBinary(mixed $configured): ?string
    {
        if (! is_string($configured)) {
            return null;
        }

        $normalized = trim($configured);
        if ($normalized === '') {
            return null;
        }

        return trim($normalized, " \t\n\r\0\x0B\"'");
    }

    private function pdfFontFileUri(string $filename): string
    {
        return $this->filePathToUri(resource_path('fonts'.DIRECTORY_SEPARATOR.$filename));
    }

    private function filePathToUri(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///'.rawurlencode(substr($normalized, 0, 1)).':'.str_replace('%2F', '/', rawurlencode(substr($normalized, 2)));
        }

        return 'file://'.$normalized;
    }
}
