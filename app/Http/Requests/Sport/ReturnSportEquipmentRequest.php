<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReturnSportEquipmentRequest extends FormRequest
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
            'returned_quantity' => $this->input('returned_quantity', $this->input('returnedQuantity')),
            'damaged_quantity' => $this->input('damaged_quantity', $this->input('damagedQuantity')),
            'missing_quantity' => $this->input('missing_quantity', $this->input('missingQuantity')),
            'admin_note' => $this->input('admin_note', $this->input('adminNote')),
        ]);
    }

    public function rules(): array
    {
        return [
            'returned_quantity' => ['required', 'integer', 'min:0'],
            'damaged_quantity' => ['required', 'integer', 'min:0'],
            'missing_quantity' => ['required', 'integer', 'min:0'],
            'admin_note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
