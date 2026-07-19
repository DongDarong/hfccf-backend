<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class FinalizePreschoolMonthlySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization checked in controller
    }

    public function rules(): array
    {
        return [
            'review_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'review_comment.max' => 'Review comment cannot exceed 1000 characters.',
        ];
    }
}
