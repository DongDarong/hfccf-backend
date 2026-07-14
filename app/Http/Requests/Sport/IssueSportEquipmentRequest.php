<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IssueSportEquipmentRequest extends FormRequest
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
            'issued_quantity' => $this->input('issued_quantity', $this->input('issuedQuantity')),
            'admin_note' => $this->input('admin_note', $this->input('adminNote')),
        ]);
    }

    public function rules(): array
    {
        return [
            'issued_quantity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
