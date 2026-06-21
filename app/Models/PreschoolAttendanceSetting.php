<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolAttendanceSetting extends Model
{
    protected $table = 'preschool_attendance_settings';

    protected $fillable = [
        'late_threshold_minutes',
        'half_day_threshold_minutes',
        'absence_alert_days',
        'guardian_alert_enabled',
        'teacher_alert_enabled',
        'admin_alert_enabled',
        'monday_enabled',
        'tuesday_enabled',
        'wednesday_enabled',
        'thursday_enabled',
        'friday_enabled',
        'saturday_enabled',
        'sunday_enabled',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'late_threshold_minutes' => 'integer',
            'half_day_threshold_minutes' => 'integer',
            'absence_alert_days' => 'integer',
            'guardian_alert_enabled' => 'boolean',
            'teacher_alert_enabled' => 'boolean',
            'admin_alert_enabled' => 'boolean',
            'monday_enabled' => 'boolean',
            'tuesday_enabled' => 'boolean',
            'wednesday_enabled' => 'boolean',
            'thursday_enabled' => 'boolean',
            'friday_enabled' => 'boolean',
            'saturday_enabled' => 'boolean',
            'sunday_enabled' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
