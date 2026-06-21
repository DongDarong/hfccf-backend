<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolPaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolPaymentSetting */
class PreschoolPaymentSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_prefix' => $this->invoice_prefix,
            'receipt_prefix' => $this->receipt_prefix,
            'next_invoice_number' => (int) $this->next_invoice_number,
            'next_receipt_number' => (int) $this->next_receipt_number,
            'late_fee_enabled' => (bool) $this->late_fee_enabled,
            'late_fee_type' => $this->late_fee_type,
            'late_fee_amount' => (float) $this->late_fee_amount,
            'grace_period_days' => (int) $this->grace_period_days,
            'proration_enabled' => (bool) $this->proration_enabled,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
