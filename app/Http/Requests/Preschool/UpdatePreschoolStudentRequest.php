<?php

namespace App\Http\Requests\Preschool;

use App\Support\CambodiaLocationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolStudentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name', ''));

        if ($name !== '' && ! $this->filled('first_name') && ! $this->filled('last_name')) {
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $this->merge([
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);
        }
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
        $studentId = (string) $this->route('id');

        return [
            'student_code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('preschool_students', 'student_code')->ignore($studentId)],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'latin_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'place_of_birth' => ['sometimes', 'nullable', 'string', 'max:200'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ethnicity' => ['sometimes', 'nullable', 'string', 'max:100'],
            'birth_province_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_provinces,id'],
            'birth_district_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_districts,id'],
            'birth_commune_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_communes,id'],
            'birth_village_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_villages,id'],
            'residence_province_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_provinces,id'],
            'residence_district_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_districts,id'],
            'residence_commune_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_communes,id'],
            'residence_village_id' => ['sometimes', 'nullable', 'integer', 'exists:cambodia_villages,id'],
            'guardian_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'guardian_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'pending', 'inactive', 'graduated'])],
            'student_type' => ['sometimes', 'nullable', Rule::in(['paying', 'non_paying'])],
            'class_ids' => ['sometimes', 'array'],
            'class_ids.*' => ['integer', 'exists:preschool_classes,id'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:4096'],
            'remove_avatar' => ['sometimes', 'nullable', 'boolean'],
            'override_locked_context' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if:override_locked_context,1', 'nullable', 'string', 'max:500'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach (['birth', 'residence'] as $prefix) {
                foreach (CambodiaLocationContract::hierarchyErrors($this->all(), $prefix) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }
}
