<?php

namespace App\Http\Requests\Preschool;

class UpdatePreschoolGradingScaleRequest extends StorePreschoolGradingScaleRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'grade' => ['sometimes', 'required', 'string', 'max:20'],
            'minimum_score' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'maximum_score' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100', 'gte:minimum_score'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_passing' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
