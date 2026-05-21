<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class AssignGovernanceIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'assigned_to_user_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
