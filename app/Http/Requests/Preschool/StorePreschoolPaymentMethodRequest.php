<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class StorePreschoolPaymentMethodRequest extends PreschoolPaymentConfigurationRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:64', Rule::unique('preschool_payment_methods', 'code')],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
