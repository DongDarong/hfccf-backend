<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolInvoiceRequest extends FormRequest
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
            'academic_year_id' => ['nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:preschool_terms,id'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:preschool_invoices,invoice_number'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
