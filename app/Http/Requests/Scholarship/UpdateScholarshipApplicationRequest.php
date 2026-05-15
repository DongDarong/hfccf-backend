<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScholarshipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminscholarship'], true);
    }

    public function rules(): array
    {
        $applicationId = (string) $this->route('id');

        return [
            'student_id' => ['sometimes', 'required', 'integer', 'exists:scholarship_students,id'],
            'application_code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('scholarship_applications', 'application_code')->ignore($applicationId)],
            'scholarship_type' => ['sometimes', 'required', 'string', 'max:100'],
            'requested_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'academic_year' => ['sometimes', 'required', 'string', 'max:20'],
            'submission_date' => ['sometimes', 'required', 'date'],
            'application_status' => ['sometimes', 'required', Rule::in(['draft', 'submitted', 'under_review', 'approved', 'rejected', 'archived'])],
            'assigned_reviewer_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'rejection_reason' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
