<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolGradingScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'grade' => ['required', 'string', 'max:20'],
            'minimum_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'maximum_score' => ['required', 'numeric', 'min:0', 'max:100', 'gte:minimum_score'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_passing' => ['required', 'boolean'],
        ];
    }
}
