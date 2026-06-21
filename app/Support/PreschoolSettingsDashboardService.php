<?php

namespace App\Support;

class PreschoolSettingsDashboardService
{
    public function __construct(
        private readonly PreschoolAttendanceConfigurationService $attendanceConfigurationService,
        private readonly PreschoolPaymentConfigurationService $paymentConfigurationService,
        private readonly PreschoolHealthConfigurationService $healthConfigurationService,
        private readonly PreschoolPreferencesService $preferencesService,
    ) {
    }

    public function getDashboard(): array
    {
        $attendance = $this->attendanceConfigurationService->getAttendanceSummary();
        $payments = $this->paymentConfigurationService->getDashboardSummary();
        $health = $this->healthConfigurationService->getDashboardSummary();
        $preferences = $this->preferencesService->getDashboardSummary();

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
                'fee_types_count' => $payments['fee_types_count'],
                'payment_methods_count' => $payments['payment_methods_count'],
                'late_fee_enabled' => $payments['late_fee_enabled'],
                'grace_period_days' => $payments['grace_period_days'],
                'invoice_prefix' => $payments['invoice_prefix'],
                'receipt_prefix' => $payments['receipt_prefix'],
                'late_fee_type' => $payments['late_fee_type'],
                'late_fee_amount' => $payments['late_fee_amount'],
                'proration_enabled' => $payments['proration_enabled'],
                'is_configured' => true,
            ],
            'assessments' => [
                'is_configured' => false,
            ],
            'health' => $health,
            'preferences' => [
                'timezone' => $preferences['timezone'],
                'default_language' => $preferences['default_language'],
                'date_format' => $preferences['date_format'],
                'time_format' => $preferences['time_format'],
                'minimum_enrollment_age_months' => $preferences['minimum_enrollment_age_months'],
                'maximum_enrollment_age_months' => $preferences['maximum_enrollment_age_months'],
                'auto_approve_enrollment' => $preferences['auto_approve_enrollment'],
                'student_code_prefix' => $preferences['student_code_prefix'],
                'student_code_year_format' => $preferences['student_code_year_format'],
                'student_code_sequence_length' => $preferences['student_code_sequence_length'],
                'student_code_preview' => $preferences['student_code_preview'],
                'default_class_capacity' => $preferences['default_class_capacity'],
                'teacher_student_ratio' => $preferences['teacher_student_ratio'],
                'waitlist_enabled' => $preferences['waitlist_enabled'],
                'minimum_guardians' => $preferences['minimum_guardians'],
                'maximum_guardians' => $preferences['maximum_guardians'],
                'primary_guardian_required' => $preferences['primary_guardian_required'],
                'pickup_authorization_required' => $preferences['pickup_authorization_required'],
                'attendance_alert_enabled' => $preferences['attendance_alert_enabled'],
                'assessment_alert_enabled' => $preferences['assessment_alert_enabled'],
                'health_alert_enabled' => $preferences['health_alert_enabled'],
                'enrollment_notification_enabled' => $preferences['enrollment_notification_enabled'],
                'enrollment_rules_label' => $preferences['enrollment_rules_label'],
                'student_code_format_label' => $preferences['student_code_format_label'],
                'class_capacity_label' => $preferences['class_capacity_label'],
                'guardian_rules_label' => $preferences['guardian_rules_label'],
                'communication_rules_label' => $preferences['communication_rules_label'],
                'is_configured' => $preferences['is_configured'],
            ],
        ];
    }
}
