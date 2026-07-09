<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolSettingsBackboneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'academicYear' => ['sometimes', 'array'],
            'terms' => ['sometimes', 'array'],
            'classConfigurations' => ['sometimes', 'array'],
            'attendance' => ['sometimes', 'array'],
            'assessment' => ['sometimes', 'array'],
            'schedule' => ['sometimes', 'array'],
            'enrollment' => ['sometimes', 'array'],
            'payment' => ['sometimes', 'array'],
            'health' => ['sometimes', 'array'],
            'preferences' => ['sometimes', 'array'],
            'preferences.timezone' => ['sometimes', 'string', 'timezone:all'],
            'preferences.defaultLanguage' => ['sometimes', 'string', Rule::in(['en', 'kh'])],
            'preferences.dateFormat' => ['sometimes', 'string', Rule::in(['Y-m-d', 'd/m/Y', 'DD/MM/YYYY'])],
            'preferences.timeFormat' => ['sometimes', 'string', Rule::in(['H:i', 'HH:mm'])],
            'preferences.minimumEnrollmentAgeMonths' => ['sometimes', 'integer', 'min:0'],
            'preferences.maximumEnrollmentAgeMonths' => ['sometimes', 'integer', 'min:0'],
            'preferences.autoApproveEnrollment' => ['sometimes', 'boolean'],
            'preferences.studentCodePrefix' => ['sometimes', 'string', 'max:16'],
            'preferences.studentCodeYearFormat' => ['sometimes', 'string', Rule::in(['YY', 'YYYY'])],
            'preferences.studentCodeSequenceLength' => ['sometimes', 'integer', 'min:1'],
            'preferences.defaultClassCapacity' => ['sometimes', 'integer', 'min:1'],
            'preferences.teacherStudentRatio' => ['sometimes', 'integer', 'min:1'],
            'preferences.waitlistEnabled' => ['sometimes', 'boolean'],
            'preferences.minimumGuardians' => ['sometimes', 'integer', 'min:0'],
            'preferences.maximumGuardians' => ['sometimes', 'integer', 'min:0'],
            'preferences.primaryGuardianRequired' => ['sometimes', 'boolean'],
            'preferences.pickupAuthorizationRequired' => ['sometimes', 'boolean'],
            'preferences.attendanceAlertEnabled' => ['sometimes', 'boolean'],
            'preferences.assessmentAlertEnabled' => ['sometimes', 'boolean'],
            'preferences.healthAlertEnabled' => ['sometimes', 'boolean'],
            'preferences.enrollmentNotificationEnabled' => ['sometimes', 'boolean'],
            'groups' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $preferences = $this->input('preferences');

            if (! is_array($preferences)) {
                return;
            }

            $minimumAge = $preferences['minimumEnrollmentAgeMonths'] ?? null;
            $maximumAge = $preferences['maximumEnrollmentAgeMonths'] ?? null;

            if (is_numeric($minimumAge) && is_numeric($maximumAge) && (int) $minimumAge > (int) $maximumAge) {
                $validator->errors()->add('preferences.maximumEnrollmentAgeMonths', 'The maximum enrollment age months must be greater than or equal to the minimum enrollment age months.');
            }

            $minimumGuardians = $preferences['minimumGuardians'] ?? null;
            $maximumGuardians = $preferences['maximumGuardians'] ?? null;

            if (is_numeric($minimumGuardians) && is_numeric($maximumGuardians) && (int) $minimumGuardians > (int) $maximumGuardians) {
                $validator->errors()->add('preferences.maximumGuardians', 'The maximum guardians must be greater than or equal to the minimum guardians.');
            }
        });
    }
}
