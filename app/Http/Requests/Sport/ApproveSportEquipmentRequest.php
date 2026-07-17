<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ApproveSportEquipmentRequest extends FormRequest
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
            'approved_quantity' => $this->input('approved_quantity', $this->input('approvedQuantity')),
            'admin_note' => $this->input('admin_note', $this->input('adminNote')),
        ]);
    }

    public function rules(): array
    {
        return [
            'approved_quantity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
