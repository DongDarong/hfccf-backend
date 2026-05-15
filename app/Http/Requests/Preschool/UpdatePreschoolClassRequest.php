<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolClassRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'teacher_display_name' => $this->input('teacher_display_name', $this->input('teacher')),
            'students_count' => $this->input('students_count', $this->input('students')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->hasPreschoolAdminAccess();
    }

    private function hasPreschoolAdminAccess(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        $classId = (string) $this->route('id');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('preschool_classes', 'code')->ignore($classId)],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'teacher_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'teacher_display_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'level' => ['sometimes', 'required', 'string', 'max:100'],
            'schedule' => ['sometimes', 'nullable', 'string', 'max:191'],
            'students_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'pending', 'closed', 'archived'])],
            'room' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => ['integer', 'exists:preschool_students,id'],
        ];
    }
}
