<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScholarshipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminscholarship'], true);
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:scholarship_students,id'],
            'application_code' => ['nullable', 'string', 'max:50', 'unique:scholarship_applications,application_code'],
            'scholarship_type' => ['required', 'string', 'max:100'],
            'requested_amount' => ['required', 'numeric', 'min:0'],
            'academic_year' => ['required', 'string', 'max:20'],
            'submission_date' => ['required', 'date'],
            'application_status' => ['nullable', Rule::in(['draft', 'submitted', 'under_review', 'approved', 'rejected', 'archived'])],
            'assigned_reviewer_user_id' => ['nullable', 'string', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ];
    }
}
