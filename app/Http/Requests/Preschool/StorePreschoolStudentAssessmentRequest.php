<?php

namespace App\Http\Requests\Preschool;

use App\Support\PreschoolAssessmentRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolStudentAssessmentRequest extends FormRequest
{
    /**
     * Assessments are created only by authenticated Preschool staff or admins.
     * Student/class ownership is checked in the service so access rules stay
     * centralized and easy to audit.
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
                'required',
                'integer',
                Rule::exists('preschool_assessment_categories', 'id')->where('is_active', true),
            ],
            'period_label' => ['required', 'string', 'max:100'],
            'assessment_date' => ['required', 'date'],
            'score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'rating' => ['sometimes', 'nullable', 'string', 'max:50', Rule::in(PreschoolAssessmentRating::values())],
            'observation' => ['sometimes', 'nullable', 'string'],
            'teacher_comment' => ['sometimes', 'nullable', 'string'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }
}
