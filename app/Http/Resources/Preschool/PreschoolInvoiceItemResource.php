<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolInvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolInvoiceItem */
class PreschoolInvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceId' => $this->invoice_id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'amount' => (float) $this->amount,
            'sortOrder' => (int) $this->sort_order,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
