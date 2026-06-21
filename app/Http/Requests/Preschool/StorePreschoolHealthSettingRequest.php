<?php

namespace App\Http\Requests\Preschool;

class StorePreschoolHealthSettingRequest extends PreschoolHealthConfigurationRequest
{
    public function rules(): array
    {
        return [
            'critical_alert_enabled' => ['required', 'boolean'],
            'guardian_notification_enabled' => ['required', 'boolean'],
            'teacher_notification_enabled' => ['required', 'boolean'],
            'admin_notification_enabled' => ['required', 'boolean'],
            'medication_reminder_enabled' => ['required', 'boolean'],
            'vaccination_reminder_enabled' => ['required', 'boolean'],
            'overdue_vaccination_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'medication_reminder_minutes_before' => ['required', 'integer', 'min:0', 'max:1440'],
        ];
    }
}
