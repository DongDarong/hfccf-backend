<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolClassRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:50', 'unique:preschool_classes,code'],
            'name' => ['required', 'string', 'max:191'],
            'teacher_user_id' => ['nullable', 'string', 'exists:users,id'],
            'teacher_display_name' => ['nullable', 'string', 'max:191'],
            'level' => ['required', 'string', 'max:100'],
            'schedule' => ['nullable', 'string', 'max:191'],
            'students_count' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'pending', 'closed', 'archived'])],
            'room' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => ['integer', 'exists:preschool_students,id'],
        ];
    }
}
