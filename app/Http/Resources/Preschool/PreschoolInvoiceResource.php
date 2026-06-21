<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolInvoice */
class PreschoolInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $studentName = trim(($this->student?->first_name ?? '').' '.($this->student?->last_name ?? ''));

        return [
            'id' => $this->id,
            'studentId' => $this->student_id,
            'studentName' => $studentName,
            'classId' => $this->class_id,
            'className' => $this->preschoolClass?->name,
            'academicYearId' => $this->academic_year_id,
            'academicYearLabel' => $this->academicYear?->label,
            'termId' => $this->term_id,
            'termLabel' => $this->term?->name,
            'invoiceNumber' => $this->invoice_number,
            'issueDate' => $this->issue_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'subtotal' => (float) $this->subtotal,
            'discountAmount' => (float) $this->discount_amount,
            'totalAmount' => (float) $this->total_amount,
            'paidAmount' => (float) $this->paid_amount,
            'balanceDue' => (float) $this->balance_due,
            'status' => $this->status,
            'items' => $this->relationLoaded('items') ? PreschoolInvoiceItemResource::collection($this->items)->resolve($request) : [],
            'payments' => $this->relationLoaded('payments') ? PreschoolPaymentResource::collection($this->payments)->resolve($request) : [],
            'receipts' => $this->relationLoaded('receipts') ? PreschoolReceiptResource::collection($this->receipts)->resolve($request) : [],
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
