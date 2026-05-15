<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        $paymentId = (string) $this->route('id');

        return [
            'student_id' => ['sometimes', 'required', 'integer', 'exists:preschool_students,id'],
            'class_id' => ['sometimes', 'required', 'integer', 'exists:preschool_classes,id'],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('preschool_payments', 'payment_reference')->ignore($paymentId)],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_method' => ['sometimes', 'required', Rule::in(['cash', 'mobile_payment', 'bank_transfer', 'card', 'other'])],
            'payment_status' => ['sometimes', 'required', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
