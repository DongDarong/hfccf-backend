<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreschoolAssessmentWeightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'weights' => ['required', 'array', 'min:1'],
            'weights.*.category_id' => ['required', 'integer', 'exists:preschool_assessment_categories,id'],
            'weights.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
