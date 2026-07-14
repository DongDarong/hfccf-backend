<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use App\Support\SportEquipmentItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSportEquipmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('equipment_code') || $this->exists('equipmentCode')) {
            $payload['equipment_code'] = $this->input('equipment_code', $this->input('equipmentCode'));
        }

        if ($this->exists('total_quantity') || $this->exists('totalQuantity')) {
            $payload['total_quantity'] = $this->input('total_quantity', $this->input('totalQuantity'));
        }

        if ($this->exists('available_quantity') || $this->exists('availableQuantity')) {
            $payload['available_quantity'] = $this->input('available_quantity', $this->input('availableQuantity'));
        }

        if ($this->exists('minimum_stock_level') || $this->exists('minimumStockLevel')) {
            $payload['minimum_stock_level'] = $this->input('minimum_stock_level', $this->input('minimumStockLevel'));
        }

        if ($this->exists('storage_location') || $this->exists('storageLocation')) {
            $payload['storage_location'] = $this->input('storage_location', $this->input('storageLocation'));
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        $itemId = $this->route('id');

        return [
            'equipment_code' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('sport_equipment_items', 'equipment_code')->ignore($itemId)],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit' => ['sometimes', 'required', 'string', 'max:32'],
            'total_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'available_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'minimum_stock_level' => ['sometimes', 'required', 'integer', 'min:0'],
            'storage_location' => ['sometimes', 'nullable', 'string', 'max:191'],
            'status' => ['sometimes', 'required', Rule::in(SportEquipmentItemStatus::values())],
        ];
    }
}
