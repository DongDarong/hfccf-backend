<?php

namespace App\Services;

use App\Models\PreschoolInvoice;
use App\Models\PreschoolInvoiceItem;
use App\Models\PreschoolPayment;
use App\Models\PreschoolReceipt;
use App\Models\PreschoolStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
        $invoice->loadMissing(['student', 'preschoolClass', 'academicYear', 'term', 'items', 'payments', 'receipts']);
        $studentName = trim(($invoice->student?->first_name ?? '').' '.($invoice->student?->last_name ?? ''));

        $rows = $invoice->items->map(static function (PreschoolInvoiceItem $item): string {
            return '<tr>'
                .'<td>'.e($item->description).'</td>'
                .'<td style="text-align:right">'.number_format((float) $item->quantity, 2).'</td>'
                .'<td style="text-align:right">'.number_format((float) $item->unit_price, 2).'</td>'
                .'<td style="text-align:right">'.number_format((float) $item->amount, 2).'</td>'
                .'</tr>';
        })->implode('');

        return $this->renderDocument('Preschool Invoice', [
            'title' => 'Preschool Invoice',
            'subtitle' => 'Invoice '.$invoice->invoice_number,
            'metadata' => [
                ['label' => 'Student', 'value' => $studentName],
                ['label' => 'Class', 'value' => $invoice->preschoolClass?->name ?? '-'],
                ['label' => 'Issue Date', 'value' => $invoice->issue_date?->toDateString() ?? '-'],
                ['label' => 'Due Date', 'value' => $invoice->due_date?->toDateString() ?? '-'],
                ['label' => 'Status', 'value' => $invoice->status],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Subtotal', 'value' => number_format((float) $invoice->subtotal, 2)],
                ['label' => 'Discount', 'value' => number_format((float) $invoice->discount_amount, 2)],
                ['label' => 'Total', 'value' => number_format((float) $invoice->total_amount, 2)],
                ['label' => 'Paid', 'value' => number_format((float) $invoice->paid_amount, 2)],
                ['label' => 'Balance', 'value' => number_format((float) $invoice->balance_due, 2)],
            ],
        ]);
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
}
