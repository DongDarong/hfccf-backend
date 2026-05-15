<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolPayment */
class PreschoolPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studentId' => $this->student_id,
            'studentName' => trim(($this->student?->first_name ?? '').' '.($this->student?->last_name ?? '')),
            'classId' => $this->class_id,
            'className' => $this->preschoolClass?->name,
            'paymentReference' => $this->payment_reference,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'paymentMethod' => $this->payment_method,
            'paymentStatus' => $this->payment_status,
            'paidAt' => $this->paid_at?->toISOString(),
            'dueDate' => $this->due_date?->toDateString(),
            'note' => $this->note,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
