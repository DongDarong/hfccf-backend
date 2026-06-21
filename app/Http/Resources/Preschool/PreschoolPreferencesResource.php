<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolPreferences;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolPreferences */
class PreschoolPreferencesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'timezone' => $this->timezone,
            'default_language' => $this->default_language,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'minimum_enrollment_age_months' => (int) $this->minimum_enrollment_age_months,
            'maximum_enrollment_age_months' => (int) $this->maximum_enrollment_age_months,
            'auto_approve_enrollment' => (bool) $this->auto_approve_enrollment,
            'student_code_prefix' => $this->student_code_prefix,
            'student_code_year_format' => $this->student_code_year_format,
            'student_code_sequence_length' => (int) $this->student_code_sequence_length,
            'default_class_capacity' => (int) $this->default_class_capacity,
            'teacher_student_ratio' => (int) $this->teacher_student_ratio,
            'waitlist_enabled' => (bool) $this->waitlist_enabled,
            'minimum_guardians' => (int) $this->minimum_guardians,
            'maximum_guardians' => (int) $this->maximum_guardians,
            'primary_guardian_required' => (bool) $this->primary_guardian_required,
            'pickup_authorization_required' => (bool) $this->pickup_authorization_required,
            'attendance_alert_enabled' => (bool) $this->attendance_alert_enabled,
            'assessment_alert_enabled' => (bool) $this->assessment_alert_enabled,
            'health_alert_enabled' => (bool) $this->health_alert_enabled,
            'enrollment_notification_enabled' => (bool) $this->enrollment_notification_enabled,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
