<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolHealthSetting extends Model
{
    protected $table = 'preschool_health_settings';

    protected $fillable = [
        'critical_alert_enabled',
        'guardian_notification_enabled',
        'teacher_notification_enabled',
        'admin_notification_enabled',
        'medication_reminder_enabled',
        'vaccination_reminder_enabled',
        'overdue_vaccination_alert_days',
        'medication_reminder_minutes_before',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'critical_alert_enabled' => 'boolean',
            'guardian_notification_enabled' => 'boolean',
            'teacher_notification_enabled' => 'boolean',
            'admin_notification_enabled' => 'boolean',
            'medication_reminder_enabled' => 'boolean',
            'vaccination_reminder_enabled' => 'boolean',
            'overdue_vaccination_alert_days' => 'integer',
            'medication_reminder_minutes_before' => 'integer',
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
