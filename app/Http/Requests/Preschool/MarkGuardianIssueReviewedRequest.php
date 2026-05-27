<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class MarkGuardianIssueReviewedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'issue_type' => ['required', 'string', 'max:100'],
            'issue_key' => ['nullable', 'string', 'max:191'],
            'student_id' => ['nullable', 'integer', 'exists:preschool_students,id'],
            'guardian_id' => ['nullable', 'integer', 'exists:preschool_guardians,id'],
            'relationship_id' => ['nullable', 'integer', 'exists:preschool_student_guardians,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
