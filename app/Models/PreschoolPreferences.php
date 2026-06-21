<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolPreferences extends Model
{
    protected $table = 'preschool_preferences';

    protected $fillable = [
        'timezone',
        'default_language',
        'date_format',
        'time_format',
        'minimum_enrollment_age_months',
        'maximum_enrollment_age_months',
        'auto_approve_enrollment',
        'student_code_prefix',
        'student_code_year_format',
        'student_code_sequence_length',
        'default_class_capacity',
        'teacher_student_ratio',
        'waitlist_enabled',
        'minimum_guardians',
        'maximum_guardians',
        'primary_guardian_required',
        'pickup_authorization_required',
        'attendance_alert_enabled',
        'assessment_alert_enabled',
        'health_alert_enabled',
        'enrollment_notification_enabled',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_enrollment_age_months' => 'integer',
            'maximum_enrollment_age_months' => 'integer',
            'auto_approve_enrollment' => 'boolean',
            'student_code_sequence_length' => 'integer',
            'default_class_capacity' => 'integer',
            'teacher_student_ratio' => 'integer',
            'waitlist_enabled' => 'boolean',
            'minimum_guardians' => 'integer',
            'maximum_guardians' => 'integer',
            'primary_guardian_required' => 'boolean',
            'pickup_authorization_required' => 'boolean',
            'attendance_alert_enabled' => 'boolean',
            'assessment_alert_enabled' => 'boolean',
            'health_alert_enabled' => 'boolean',
            'enrollment_notification_enabled' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
