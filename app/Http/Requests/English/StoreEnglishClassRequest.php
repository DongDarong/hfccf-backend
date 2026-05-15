<?php

namespace App\Http\Requests\English;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnglishClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && in_array($user->role_code, ['superadmin', 'adminenglish'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'class_code' => $this->input('class_code', $this->input('classCode')),
        ]);
    }

    public function rules(): array
    {
        return [
            'class_code' => ['nullable', 'string', 'max:32', 'unique:english_classes,class_code'],
            'name' => ['required', 'string', 'max:150'],
            'level' => ['required', 'string', 'max:100'],
            'teacher_user_id' => ['nullable', 'string', 'exists:users,id'],
            'schedule' => ['nullable', 'string', 'max:191'],
            'room' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
            'description' => ['nullable', 'string'],
            'student_ids' => ['sometimes', 'array'],
            'student_ids.*' => ['integer', 'exists:english_students,id'],
        ];
    }
}
