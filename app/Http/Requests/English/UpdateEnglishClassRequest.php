<?php

namespace App\Http\Requests\English;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnglishClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && in_array($user->role_code, ['superadmin', 'adminenglish'], true);
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('class_code') || $this->exists('classCode')) {
            $payload['class_code'] = $this->input('class_code', $this->input('classCode'));
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        $classId = (string) $this->route('id');

        return [
            'class_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:english_classes,class_code,'.$classId],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'level' => ['sometimes', 'required', 'string', 'max:100'],
            'teacher_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'schedule' => ['sometimes', 'nullable', 'string', 'max:191'],
            'room' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'archived'])],
            'description' => ['sometimes', 'nullable', 'string'],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => ['integer', 'exists:english_students,id'],
        ];
    }
}
