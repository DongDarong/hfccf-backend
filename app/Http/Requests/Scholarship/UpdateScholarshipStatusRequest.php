<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScholarshipStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminscholarship'], true);
    }

    public function rules(): array
    {
        return [
            'application_status' => ['sometimes', 'nullable', Rule::in(['draft', 'submitted', 'under_review', 'approved', 'rejected', 'archived'])],
            'rejection_reason' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'assigned_reviewer_user_id' => ['nullable', 'string', 'exists:users,id'],
        ];
    }
}
