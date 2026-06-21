<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:preschool_students,id'],
            'class_id' => ['required', 'integer', 'exists:preschool_classes,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:preschool_invoices,id'],
            'payment_reference' => ['nullable', 'string', 'max:100', 'unique:preschool_payments,payment_reference'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_method' => ['required', Rule::in(['cash', 'mobile_payment', 'bank_transfer', 'card', 'other'])],
            'payment_status' => ['required', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
            'paid_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
