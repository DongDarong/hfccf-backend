<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolAssessmentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'grading_scale_type' => ['required', 'string', 'in:percentage,letter,custom'],
            'weighting_enabled' => ['required', 'boolean'],
        ];
    }
}
