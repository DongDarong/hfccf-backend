<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class ReturnPreschoolMonthlySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization checked in controller
    }

    public function rules(): array
    {
        return [
            'return_reason' => ['required', 'string', 'max:500'],
            'review_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'return_reason.required' => 'Return reason is required.',
            'return_reason.max' => 'Return reason cannot exceed 500 characters.',
            'review_comment.max' => 'Review comment cannot exceed 1000 characters.',
        ];
    }
}
