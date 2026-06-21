<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class UpdatePreschoolFeeTypeRequest extends StorePreschoolFeeTypeRequest
{
    public function rules(): array
    {
        $feeType = $this->route('feeType');
        $feeTypeId = is_object($feeType) ? $feeType->id : $feeType;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:64', Rule::unique('preschool_fee_types', 'code')->ignore($feeTypeId)],
            'description' => ['nullable', 'string'],
            'default_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
