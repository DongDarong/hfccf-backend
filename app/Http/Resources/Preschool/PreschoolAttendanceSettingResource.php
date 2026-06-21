<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAttendanceSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolAttendanceSetting */
class PreschoolAttendanceSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enabledDays = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            if ((bool) $this->{$day.'_enabled'}) {
                $enabledDays[] = $day;
            }
        }

        return [
            'id' => $this->id,
            'late_threshold_minutes' => $this->late_threshold_minutes,
            'half_day_threshold_minutes' => $this->half_day_threshold_minutes,
            'absence_alert_days' => $this->absence_alert_days,
            'guardian_alert_enabled' => (bool) $this->guardian_alert_enabled,
            'teacher_alert_enabled' => (bool) $this->teacher_alert_enabled,
            'admin_alert_enabled' => (bool) $this->admin_alert_enabled,
            'school_week' => [
                'monday_enabled' => (bool) $this->monday_enabled,
                'tuesday_enabled' => (bool) $this->tuesday_enabled,
                'wednesday_enabled' => (bool) $this->wednesday_enabled,
                'thursday_enabled' => (bool) $this->thursday_enabled,
                'friday_enabled' => (bool) $this->friday_enabled,
                'saturday_enabled' => (bool) $this->saturday_enabled,
                'sunday_enabled' => (bool) $this->sunday_enabled,
                'enabled_days' => $enabledDays,
                'school_days_per_week' => count($enabledDays),
            ],
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
