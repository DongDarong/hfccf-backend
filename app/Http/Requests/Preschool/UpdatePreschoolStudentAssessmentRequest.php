<?php

namespace App\Http\Requests\Preschool;

use App\Support\PreschoolAssessmentRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolStudentAssessmentRequest extends FormRequest
{
    /**
     * Draft assessments remain editable for Preschool staff, while finalized
     * records are protected later in the service to keep history immutable.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true);
    }

    public function rules(): array
    {
        return [
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('preschool_assessment_categories', 'id')->where('is_active', true),
            ],
            'period_label' => ['sometimes', 'required', 'string', 'max:100'],
            'assessment_date' => ['sometimes', 'required', 'date'],
            'score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'rating' => ['sometimes', 'nullable', 'string', 'max:50', Rule::in(PreschoolAssessmentRating::values())],
            'observation' => ['sometimes', 'nullable', 'string'],
            'teacher_comment' => ['sometimes', 'nullable', 'string'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }
}
