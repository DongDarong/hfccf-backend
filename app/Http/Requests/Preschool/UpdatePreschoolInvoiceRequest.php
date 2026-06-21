<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        $invoiceId = (string) $this->route('invoice');

        return [
            'student_id' => ['sometimes', 'required', 'integer', 'exists:preschool_students,id'],
            'class_id' => ['sometimes', 'required', 'integer', 'exists:preschool_classes,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('preschool_invoices', 'invoice_number')->ignore($invoiceId)],
            'issue_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
