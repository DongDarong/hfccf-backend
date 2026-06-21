<?php

namespace App\Http\Requests\Preschool;

class UpdatePreschoolBillingRulesRequest extends PreschoolPaymentConfigurationRequest
{
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.rule_name' => ['required', 'string', 'max:191'],
            'rules.*.rule_code' => ['required', 'string', 'max:64'],
            'rules.*.rule_value' => ['required', 'string', 'max:191'],
            'rules.*.description' => ['nullable', 'string'],
            'rules.*.is_active' => ['required', 'boolean'],
        ];
    }
}
