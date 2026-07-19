<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class UpsertPreschoolMonthlySubmissionScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization checked in controller
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'teacher_comment' => ['nullable', 'string', 'max:1000'],
            'assessment_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'score.numeric' => 'Score must be a number.',
            'score.min' => 'Score cannot be negative.',
            'score.max' => 'Score cannot exceed 100.',
            'observation.max' => 'Observation cannot exceed 1000 characters.',
            'teacher_comment.max' => 'Comment cannot exceed 1000 characters.',
            'assessment_date.date' => 'Assessment date must be a valid date.',
        ];
    }
}
