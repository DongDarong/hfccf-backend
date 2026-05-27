<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'class_id' => ['sometimes', 'required', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'required', 'integer', 'exists:preschool_students,id'],
            'attendance_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'note' => ['sometimes', 'nullable', 'string'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }
}
