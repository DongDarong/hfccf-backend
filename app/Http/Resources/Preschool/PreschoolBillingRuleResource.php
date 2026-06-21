<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolBillingRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolBillingRule */
class PreschoolBillingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_name' => $this->rule_name,
            'rule_code' => $this->rule_code,
            'rule_value' => $this->rule_value,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
