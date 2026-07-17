<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RejectSportEquipmentRequest extends FormRequest
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
            'rejected_reason' => $this->input('rejected_reason', $this->input('rejectionReason')),
            'admin_note' => $this->input('admin_note', $this->input('adminNote')),
        ]);
    }

    public function rules(): array
    {
        return [
            'rejected_reason' => ['required', 'string', 'max:2000'],
            'admin_note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
