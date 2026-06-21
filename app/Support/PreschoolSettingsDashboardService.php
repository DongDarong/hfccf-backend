<?php

namespace App\Support;

class PreschoolSettingsDashboardService
{
    public function __construct(
        private readonly PreschoolAttendanceConfigurationService $attendanceConfigurationService,
        private readonly PreschoolHealthConfigurationService $healthConfigurationService,
    ) {
    }

    public function getDashboard(): array
    {
        $attendance = $this->attendanceConfigurationService->getAttendanceSummary();
        $health = $this->healthConfigurationService->getDashboardSummary();

        return [
            'academic' => [
                'is_configured' => false,
            ],
            'attendance' => [
                'late_threshold_minutes' => $attendance['late_threshold_minutes'],
                'absence_alert_days' => $attendance['absence_alert_days'],
                'school_days_per_week' => $attendance['school_days_per_week'],
                'calendar_events_count' => $attendance['calendar_events_count'],
                'school_week' => $attendance['school_week'],
                'school_week_label' => implode(', ', $attendance['school_week']),
                'is_configured' => true,
            ],
            'payments' => [
                'is_configured' => false,
            ],
            'assessments' => [
                'is_configured' => false,
            ],
            'health' => $health,
            'preferences' => [
                'is_configured' => false,
            ],
        ];
    }
}
