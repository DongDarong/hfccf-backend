<?php

namespace App\Http\Requests\Preschool;

class StorePreschoolPaymentSettingRequest extends PreschoolPaymentConfigurationRequest
{
    public function rules(): array
    {
        return [
            'invoice_prefix' => ['required', 'string', 'max:32'],
            'receipt_prefix' => ['required', 'string', 'max:32'],
            'next_invoice_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'next_receipt_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'late_fee_enabled' => ['required', 'boolean'],
            'late_fee_type' => ['required', 'string', 'in:fixed,percentage'],
            'late_fee_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'proration_enabled' => ['required', 'boolean'],
        ];
    }
}
