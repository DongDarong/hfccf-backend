<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScholarshipReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', 'exists:scholarship_applications,id'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'recommendation' => ['required', Rule::in(['approve', 'review', 'reject'])],
            'review_note' => ['nullable', 'string'],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }
}
