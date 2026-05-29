<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['required', 'integer', 'exists:preschool_students,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'note' => ['nullable', 'string'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }
}
