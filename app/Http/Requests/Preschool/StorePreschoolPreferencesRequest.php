<?php

namespace App\Http\Requests\Preschool;

class StorePreschoolPreferencesRequest extends PreschoolPreferencesConfigurationRequest
{
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'max:64'],
            'default_language' => ['required', 'string', 'max:16'],
            'date_format' => ['required', 'string', 'max:32'],
            'time_format' => ['required', 'string', 'max:32'],
            'minimum_enrollment_age_months' => ['required', 'integer', 'min:0', 'max:240'],
            'maximum_enrollment_age_months' => ['required', 'integer', 'min:0', 'max:240'],
            'auto_approve_enrollment' => ['required', 'boolean'],
            'student_code_prefix' => ['required', 'string', 'max:32'],
            'student_code_year_format' => ['required', 'string', 'max:32'],
            'student_code_sequence_length' => ['required', 'integer', 'min:1', 'max:12'],
            'default_class_capacity' => ['required', 'integer', 'min:1', 'max:999'],
            'teacher_student_ratio' => ['required', 'integer', 'min:1', 'max:999'],
            'waitlist_enabled' => ['required', 'boolean'],
            'minimum_guardians' => ['required', 'integer', 'min:0', 'max:10'],
            'maximum_guardians' => ['required', 'integer', 'min:0', 'max:10'],
            'primary_guardian_required' => ['required', 'boolean'],
            'pickup_authorization_required' => ['required', 'boolean'],
            'attendance_alert_enabled' => ['required', 'boolean'],
            'assessment_alert_enabled' => ['required', 'boolean'],
            'health_alert_enabled' => ['required', 'boolean'],
            'enrollment_notification_enabled' => ['required', 'boolean'],
        ];
    }
}
