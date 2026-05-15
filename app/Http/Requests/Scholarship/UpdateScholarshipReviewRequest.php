<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScholarshipReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $reviewId = (string) $this->route('id');

        return [
            'application_id' => ['sometimes', 'required', 'integer', 'exists:scholarship_applications,id'],
            'score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'recommendation' => ['sometimes', 'required', Rule::in(['approve', 'review', 'reject'])],
            'review_note' => ['sometimes', 'nullable', 'string'],
            'reviewed_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
