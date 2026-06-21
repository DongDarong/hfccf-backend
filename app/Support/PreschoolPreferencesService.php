<?php

namespace App\Support;

use App\Models\PreschoolPreferences;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PreschoolPreferencesService
{
    public const DEFAULT_SETTINGS = [
        'timezone' => 'Asia/Phnom_Penh',
        'default_language' => 'en',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'minimum_enrollment_age_months' => 24,
        'maximum_enrollment_age_months' => 60,
        'auto_approve_enrollment' => false,
        'student_code_prefix' => 'PS',
        'student_code_year_format' => 'YYYY',
        'student_code_sequence_length' => 4,
        'default_class_capacity' => 18,
        'teacher_student_ratio' => 10,
        'waitlist_enabled' => true,
        'minimum_guardians' => 1,
        'maximum_guardians' => 2,
        'primary_guardian_required' => true,
        'pickup_authorization_required' => true,
        'attendance_alert_enabled' => true,
        'assessment_alert_enabled' => true,
        'health_alert_enabled' => true,
        'enrollment_notification_enabled' => true,
    ];

    public function getPreferences(): PreschoolPreferences
    {
        $record = PreschoolPreferences::query()->first();

        if ($record) {
            return $record;
        }

        return new PreschoolPreferences(self::DEFAULT_SETTINGS);
    }

    public function updatePreferences(array $data, ?User $actor = null): PreschoolPreferences
    {
        return DB::transaction(function () use ($data, $actor): PreschoolPreferences {
            $payload = $this->normalizePayload($data);
            $record = PreschoolPreferences::query()->first();

            if ($record) {
                $record->fill($payload);
                $record->updated_by = $actor?->getKey();
                if (! $record->created_by && $actor?->getKey()) {
                    $record->created_by = $actor->getKey();
                }
                $record->save();

                return $record->refresh();
            }

            $payload['created_by'] = $actor?->getKey();
            $payload['updated_by'] = $actor?->getKey();

            return PreschoolPreferences::query()->create($payload);
        });
    }

    public function getMinimumEnrollmentAge(): int
    {
        return (int) $this->getPreferences()->minimum_enrollment_age_months;
    }

    public function getMaximumEnrollmentAge(): int
    {
        return (int) $this->getPreferences()->maximum_enrollment_age_months;
    }

    public function shouldAutoApproveEnrollment(): bool
    {
        return (bool) $this->getPreferences()->auto_approve_enrollment;
    }

    public function getStudentCodePrefix(): string
    {
        return (string) $this->getPreferences()->student_code_prefix;
    }

    public function getStudentCodeYearFormat(): string
    {
        return (string) $this->getPreferences()->student_code_year_format;
    }

    public function getStudentCodeSequenceLength(): int
    {
        return max(1, (int) $this->getPreferences()->student_code_sequence_length);
    }

    public function generateStudentCode(?int $sequence = null, ?CarbonInterface $date = null): string
    {
        $settings = $this->getPreferences();
        $sequence = max(1, (int) ($sequence ?? 1));
        $year = $this->formatStudentCodeYear($settings->student_code_year_format, $date);

        return sprintf(
            '%s-%s-%s',
            strtoupper(trim((string) $settings->student_code_prefix ?: self::DEFAULT_SETTINGS['student_code_prefix'])),
            $year,
            str_pad((string) $sequence, $this->getStudentCodeSequenceLength(), '0', STR_PAD_LEFT),
        );
    }

    public function getDefaultClassCapacity(): int
    {
        return (int) $this->getPreferences()->default_class_capacity;
    }

    public function getTeacherStudentRatio(): int
    {
        return (int) $this->getPreferences()->teacher_student_ratio;
    }

    public function isWaitlistEnabled(): bool
    {
        return (bool) $this->getPreferences()->waitlist_enabled;
    }

    public function getMinimumGuardians(): int
    {
        return (int) $this->getPreferences()->minimum_guardians;
    }

    public function getMaximumGuardians(): int
    {
        return (int) $this->getPreferences()->maximum_guardians;
    }

    public function isPrimaryGuardianRequired(): bool
    {
        return (bool) $this->getPreferences()->primary_guardian_required;
    }

    public function isPickupAuthorizationRequired(): bool
    {
        return (bool) $this->getPreferences()->pickup_authorization_required;
    }

    public function attendanceAlertsEnabled(): bool
    {
        return (bool) $this->getPreferences()->attendance_alert_enabled;
    }

    public function assessmentAlertsEnabled(): bool
    {
        return (bool) $this->getPreferences()->assessment_alert_enabled;
    }

    public function healthAlertsEnabled(): bool
    {
        return (bool) $this->getPreferences()->health_alert_enabled;
    }

    public function enrollmentNotificationsEnabled(): bool
    {
        return (bool) $this->getPreferences()->enrollment_notification_enabled;
    }

    public function getDashboardSummary(): array
    {
        $settings = $this->getPreferences();

        return [
            'timezone' => $settings->timezone,
            'default_language' => $settings->default_language,
            'date_format' => $settings->date_format,
            'time_format' => $settings->time_format,
            'minimum_enrollment_age_months' => (int) $settings->minimum_enrollment_age_months,
            'maximum_enrollment_age_months' => (int) $settings->maximum_enrollment_age_months,
            'auto_approve_enrollment' => (bool) $settings->auto_approve_enrollment,
            'student_code_prefix' => $settings->student_code_prefix,
            'student_code_year_format' => $settings->student_code_year_format,
            'student_code_sequence_length' => (int) $settings->student_code_sequence_length,
            'student_code_preview' => $this->generateStudentCode(1),
            'default_class_capacity' => (int) $settings->default_class_capacity,
            'teacher_student_ratio' => (int) $settings->teacher_student_ratio,
            'waitlist_enabled' => (bool) $settings->waitlist_enabled,
            'minimum_guardians' => (int) $settings->minimum_guardians,
            'maximum_guardians' => (int) $settings->maximum_guardians,
            'primary_guardian_required' => (bool) $settings->primary_guardian_required,
            'pickup_authorization_required' => (bool) $settings->pickup_authorization_required,
            'attendance_alert_enabled' => (bool) $settings->attendance_alert_enabled,
            'assessment_alert_enabled' => (bool) $settings->assessment_alert_enabled,
            'health_alert_enabled' => (bool) $settings->health_alert_enabled,
            'enrollment_notification_enabled' => (bool) $settings->enrollment_notification_enabled,
            'enrollment_rules_label' => sprintf(
                '%d-%d months%s',
                (int) $settings->minimum_enrollment_age_months,
                (int) $settings->maximum_enrollment_age_months,
                $settings->auto_approve_enrollment ? ' • Auto-approve' : '',
            ),
            'student_code_format_label' => $this->generateStudentCode(1),
            'class_capacity_label' => sprintf(
                '%d students • 1:%d ratio%s',
                (int) $settings->default_class_capacity,
                (int) $settings->teacher_student_ratio,
                $settings->waitlist_enabled ? ' • Waitlist enabled' : ' • Waitlist disabled',
            ),
            'guardian_rules_label' => sprintf(
                'Min %d • Max %d%s%s',
                (int) $settings->minimum_guardians,
                (int) $settings->maximum_guardians,
                $settings->primary_guardian_required ? ' • Primary guardian required' : '',
                $settings->pickup_authorization_required ? ' • Pickup authorization required' : '',
            ),
            'communication_rules_label' => sprintf(
                'Attendance: %s • Assessment: %s • Health: %s • Enrollment: %s',
                $settings->attendance_alert_enabled ? 'On' : 'Off',
                $settings->assessment_alert_enabled ? 'On' : 'Off',
                $settings->health_alert_enabled ? 'On' : 'Off',
                $settings->enrollment_notification_enabled ? 'On' : 'Off',
            ),
            'is_configured' => true,
        ];
    }

    private function normalizePayload(array $data): array
    {
        return [
            'timezone' => trim((string) Arr::get($data, 'timezone', self::DEFAULT_SETTINGS['timezone'])),
            'default_language' => trim((string) Arr::get($data, 'default_language', self::DEFAULT_SETTINGS['default_language'])),
            'date_format' => trim((string) Arr::get($data, 'date_format', self::DEFAULT_SETTINGS['date_format'])),
            'time_format' => trim((string) Arr::get($data, 'time_format', self::DEFAULT_SETTINGS['time_format'])),
            'minimum_enrollment_age_months' => max(0, (int) Arr::get($data, 'minimum_enrollment_age_months', self::DEFAULT_SETTINGS['minimum_enrollment_age_months'])),
            'maximum_enrollment_age_months' => max(0, (int) Arr::get($data, 'maximum_enrollment_age_months', self::DEFAULT_SETTINGS['maximum_enrollment_age_months'])),
            'auto_approve_enrollment' => (bool) Arr::get($data, 'auto_approve_enrollment', self::DEFAULT_SETTINGS['auto_approve_enrollment']),
            'student_code_prefix' => strtoupper(trim((string) Arr::get($data, 'student_code_prefix', self::DEFAULT_SETTINGS['student_code_prefix']))),
            'student_code_year_format' => strtoupper(trim((string) Arr::get($data, 'student_code_year_format', self::DEFAULT_SETTINGS['student_code_year_format']))),
            'student_code_sequence_length' => max(1, (int) Arr::get($data, 'student_code_sequence_length', self::DEFAULT_SETTINGS['student_code_sequence_length'])),
            'default_class_capacity' => max(1, (int) Arr::get($data, 'default_class_capacity', self::DEFAULT_SETTINGS['default_class_capacity'])),
            'teacher_student_ratio' => max(1, (int) Arr::get($data, 'teacher_student_ratio', self::DEFAULT_SETTINGS['teacher_student_ratio'])),
            'waitlist_enabled' => (bool) Arr::get($data, 'waitlist_enabled', self::DEFAULT_SETTINGS['waitlist_enabled']),
            'minimum_guardians' => max(0, (int) Arr::get($data, 'minimum_guardians', self::DEFAULT_SETTINGS['minimum_guardians'])),
            'maximum_guardians' => max(0, (int) Arr::get($data, 'maximum_guardians', self::DEFAULT_SETTINGS['maximum_guardians'])),
            'primary_guardian_required' => (bool) Arr::get($data, 'primary_guardian_required', self::DEFAULT_SETTINGS['primary_guardian_required']),
            'pickup_authorization_required' => (bool) Arr::get($data, 'pickup_authorization_required', self::DEFAULT_SETTINGS['pickup_authorization_required']),
            'attendance_alert_enabled' => (bool) Arr::get($data, 'attendance_alert_enabled', self::DEFAULT_SETTINGS['attendance_alert_enabled']),
            'assessment_alert_enabled' => (bool) Arr::get($data, 'assessment_alert_enabled', self::DEFAULT_SETTINGS['assessment_alert_enabled']),
            'health_alert_enabled' => (bool) Arr::get($data, 'health_alert_enabled', self::DEFAULT_SETTINGS['health_alert_enabled']),
            'enrollment_notification_enabled' => (bool) Arr::get($data, 'enrollment_notification_enabled', self::DEFAULT_SETTINGS['enrollment_notification_enabled']),
        ];
    }

    private function formatStudentCodeYear(string $yearFormat, ?CarbonInterface $date = null): string
    {
        $carbon = $date instanceof CarbonInterface ? Carbon::instance($date) : Carbon::now();
        $format = strtoupper(trim($yearFormat));

        return match ($format) {
            'YY' => $carbon->format('y'),
            'YYYY' => $carbon->format('Y'),
            default => $format,
        };
    }
}
