<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolReceipt */
class PreschoolReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $studentName = trim(($this->payment?->student?->first_name ?? '').' '.($this->payment?->student?->last_name ?? ''));

        return [
            'id' => $this->id,
            'paymentId' => $this->payment_id,
            'invoiceId' => $this->invoice_id,
            'studentName' => $studentName,
            'invoiceNumber' => $this->invoice?->invoice_number,
            'paymentReference' => $this->payment?->payment_reference,
            'receiptNumber' => $this->receipt_number,
            'issuedAt' => $this->issued_at?->toISOString(),
            'issuedBy' => $this->issued_by,
            'amount' => (float) $this->amount,
            'paymentMethod' => $this->payment_method,
            'notes' => $this->notes,
            'reissuedFromReceiptId' => $this->reissued_from_receipt_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
