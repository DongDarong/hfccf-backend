<?php

namespace App\Http\Requests\Preschool;

use App\Support\CambodiaLocationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolEnrollmentApplicationRequest extends FormRequest
{
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'khmer_name' => ['nullable', 'string', 'max:200'],
            'latin_name' => ['nullable', 'string', 'max:200'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:200'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'birth_province_id' => ['nullable', 'integer', 'exists:cambodia_provinces,id'],
            'birth_district_id' => ['nullable', 'integer', 'exists:cambodia_districts,id'],
            'birth_commune_id' => ['nullable', 'integer', 'exists:cambodia_communes,id'],
            'birth_village_id' => ['nullable', 'integer', 'exists:cambodia_villages,id'],
            'residence_province_id' => ['nullable', 'integer', 'exists:cambodia_provinces,id'],
            'residence_district_id' => ['nullable', 'integer', 'exists:cambodia_districts,id'],
            'residence_commune_id' => ['nullable', 'integer', 'exists:cambodia_communes,id'],
            'residence_village_id' => ['nullable', 'integer', 'exists:cambodia_villages,id'],
            'requested_academic_year_id' => ['nullable', 'integer', 'exists:preschool_academic_years,id'],
            'requested_term_id' => ['nullable', 'integer', 'exists:preschool_terms,id'],
            'requested_level' => ['nullable', 'string', 'max:100'],
            'preferred_class_id' => ['nullable', 'integer', 'exists:preschool_classes,id'],
            'requested_start_date' => ['nullable', 'date'],
            'guardian_name' => ['nullable', 'string', 'max:200'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email', 'max:200'],
            'guardian_address' => ['nullable', 'string', 'max:500'],
            'guardian_can_pickup' => ['nullable', 'boolean'],
            'guardian_is_emergency' => ['nullable', 'boolean'],
            'application_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
            'admin_notes' => ['nullable', 'string'],
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
