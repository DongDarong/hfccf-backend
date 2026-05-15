<?php

namespace App\Http\Requests\English;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnglishStudentRequest extends FormRequest
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

        if ($this->exists('first_name') || $this->exists('firstName')) {
            $payload['first_name'] = $this->input('first_name', $this->input('firstName'));
        }

        if ($this->exists('last_name') || $this->exists('lastName')) {
            $payload['last_name'] = $this->input('last_name', $this->input('lastName'));
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        $studentId = (string) $this->route('id');

        return [
            'student_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:english_students,student_code,'.$studentId],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'guardian_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'guardian_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'archived'])],
            'class_ids' => ['sometimes', 'array'],
            'class_ids.*' => ['integer', 'exists:english_classes,id'],
        ];
    }
}
