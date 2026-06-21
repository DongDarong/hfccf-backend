<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolHealthSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolHealthSetting */
class PreschoolHealthSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'critical_alert_enabled' => (bool) $this->critical_alert_enabled,
            'guardian_notification_enabled' => (bool) $this->guardian_notification_enabled,
            'teacher_notification_enabled' => (bool) $this->teacher_notification_enabled,
            'admin_notification_enabled' => (bool) $this->admin_notification_enabled,
            'medication_reminder_enabled' => (bool) $this->medication_reminder_enabled,
            'vaccination_reminder_enabled' => (bool) $this->vaccination_reminder_enabled,
            'overdue_vaccination_alert_days' => (int) $this->overdue_vaccination_alert_days,
            'medication_reminder_minutes_before' => (int) $this->medication_reminder_minutes_before,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
