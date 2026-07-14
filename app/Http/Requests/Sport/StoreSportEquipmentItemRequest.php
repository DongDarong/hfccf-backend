<?php

namespace App\Http\Requests\Sport;

use App\Support\SportEquipmentItemStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSportEquipmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'equipment_code' => $this->input('equipment_code', $this->input('equipmentCode')),
            'total_quantity' => $this->input('total_quantity', $this->input('totalQuantity')),
            'available_quantity' => $this->input('available_quantity', $this->input('availableQuantity')),
            'minimum_stock_level' => $this->input('minimum_stock_level', $this->input('minimumStockLevel')),
            'storage_location' => $this->input('storage_location', $this->input('storageLocation')),
        ]);
    }

    public function rules(): array
    {
        return [
            'equipment_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_equipment_items,equipment_code'],
            'name' => ['required', 'string', 'max:191'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit' => ['required', 'string', 'max:32'],
            'total_quantity' => ['required', 'integer', 'min:0'],
            'available_quantity' => ['required', 'integer', 'min:0'],
            'minimum_stock_level' => ['required', 'integer', 'min:0'],
            'storage_location' => ['sometimes', 'nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in(SportEquipmentItemStatus::values())],
        ];
    }
}
