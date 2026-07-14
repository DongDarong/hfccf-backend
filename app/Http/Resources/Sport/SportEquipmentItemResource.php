<?php

namespace App\Http\Resources\Sport;

use App\Models\SportEquipmentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportEquipmentItem */
class SportEquipmentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availableQuantity = (int) $this->available_quantity;
        $minimumStockLevel = (int) $this->minimum_stock_level;

        return [
            'id' => $this->id,
            'equipmentCode' => $this->equipment_code,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'unit' => $this->unit,
            'totalQuantity' => (int) $this->total_quantity,
            'availableQuantity' => $availableQuantity,
            'minimumStockLevel' => $minimumStockLevel,
            'storageLocation' => $this->storage_location,
            'status' => $this->status,
            'isLowStock' => $availableQuantity <= $minimumStockLevel,
            'isOutOfStock' => $availableQuantity === 0,
            'createdByUserId' => $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id,
            'createdBy' => $this->whenLoaded('createdBy', fn (): array => [
                'id' => $this->createdBy?->id,
                'firstName' => $this->createdBy?->first_name,
                'lastName' => $this->createdBy?->last_name,
                'username' => $this->createdBy?->username,
                'email' => $this->createdBy?->email,
            ]),
            'updatedBy' => $this->whenLoaded('updatedBy', fn (): array => [
                'id' => $this->updatedBy?->id,
                'firstName' => $this->updatedBy?->first_name,
                'lastName' => $this->updatedBy?->last_name,
                'username' => $this->updatedBy?->username,
                'email' => $this->updatedBy?->email,
            ]),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
