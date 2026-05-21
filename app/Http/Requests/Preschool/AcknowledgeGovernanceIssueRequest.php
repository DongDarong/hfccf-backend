<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeGovernanceIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
