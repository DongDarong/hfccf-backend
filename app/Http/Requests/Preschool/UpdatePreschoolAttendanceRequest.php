<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true);
    }

    public function rules(): array
    {
        return [
            'attendance_session_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_attendance_sessions,id'],
            'class_id' => ['sometimes', 'required_without:attendance_session_id', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'required', 'integer', 'exists:preschool_students,id'],
            'attendance_date' => ['sometimes', 'required_without:attendance_session_id', 'nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'note' => ['sometimes', 'nullable', 'string'],
            'finalize' => ['sometimes', 'boolean'],
            'complete' => ['sometimes', 'boolean'],
            'submit' => ['sometimes', 'boolean'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }
}
