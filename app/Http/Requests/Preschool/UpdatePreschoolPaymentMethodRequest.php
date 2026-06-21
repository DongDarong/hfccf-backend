<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class UpdatePreschoolPaymentMethodRequest extends StorePreschoolPaymentMethodRequest
{
    public function rules(): array
    {
        $method = $this->route('method');
        $methodId = is_object($method) ? $method->id : $method;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:64', Rule::unique('preschool_payment_methods', 'code')->ignore($methodId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
