<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSportEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && $user->role_code === 'coach';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'equipment_item_id' => $this->input('equipment_item_id', $this->input('equipmentItemId')),
            'team_id' => $this->input('team_id', $this->input('teamId')),
            'requested_quantity' => $this->input('requested_quantity', $this->input('requestedQuantity')),
            'approved_quantity' => $this->input('approved_quantity', $this->input('approvedQuantity')),
            'issued_quantity' => $this->input('issued_quantity', $this->input('issuedQuantity')),
            'returned_quantity' => $this->input('returned_quantity', $this->input('returnedQuantity')),
            'damaged_quantity' => $this->input('damaged_quantity', $this->input('damagedQuantity')),
            'missing_quantity' => $this->input('missing_quantity', $this->input('missingQuantity')),
            'required_date' => $this->input('required_date', $this->input('requiredDate')),
            'expected_return_date' => $this->input('expected_return_date', $this->input('expectedReturnDate')),
        ]);
    }

    public function rules(): array
    {
        return [
            'equipment_item_id' => ['required', 'integer', 'exists:sport_equipment_items,id'],
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'purpose' => ['required', 'string', 'max:2000'],
            'required_date' => ['required', 'date'],
            'expected_return_date' => ['required', 'date', 'after_or_equal:required_date'],
        ];
    }
}
