<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolAttendanceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'late_threshold_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'half_day_threshold_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'absence_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'guardian_alert_enabled' => ['required', 'boolean'],
            'teacher_alert_enabled' => ['required', 'boolean'],
            'admin_alert_enabled' => ['required', 'boolean'],
            'monday_enabled' => ['required', 'boolean'],
            'tuesday_enabled' => ['required', 'boolean'],
            'wednesday_enabled' => ['required', 'boolean'],
            'thursday_enabled' => ['required', 'boolean'],
            'friday_enabled' => ['required', 'boolean'],
            'saturday_enabled' => ['required', 'boolean'],
            'sunday_enabled' => ['required', 'boolean'],
        ];
    }
}
