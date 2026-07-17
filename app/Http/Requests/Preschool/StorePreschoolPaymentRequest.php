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
            'mode' => ['sometimes', 'nullable', Rule::in(['existing_invoice', 'quick_invoice'])],
            'student_id' => ['required', 'integer', 'exists:preschool_students,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'invoice_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_invoices,id'],
            'payment_reference' => ['nullable', 'string', 'max:100', 'unique:preschool_payments,payment_reference'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_method' => ['required', Rule::in(['cash', 'mobile_payment', 'bank_transfer', 'card', 'other'])],
            'payment_status' => ['sometimes', 'nullable', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'issue_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string'],
        ];
    }
}
