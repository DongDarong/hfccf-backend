<?php

namespace App\Support;

use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolSettingsBackbone;
use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;

class PreschoolSettingsDashboardService
{
    public function summary(): array
    {
        $backboneService = app(PreschoolSettingsBackboneService::class);
        $snapshot = $backboneService->snapshot();
        $record = PreschoolSettingsBackbone::query()
            ->where('key', PreschoolSettingsBackboneService::BACKBONE_KEY)
            ->first();
        $attendanceService = app(PreschoolAttendanceConfigurationService::class);
        $attendanceSettings = $attendanceService->getSettings();
        $attendanceSummary = $attendanceService->getAttendanceSummary();

        $academicLifecycle = app(PreschoolAcademicLifecycleService::class);
        $activeAcademicYear = $academicLifecycle->currentAcademicYear();
        $activeTerm = $academicLifecycle->currentTerm($activeAcademicYear?->id);
        $assessmentCategories = PreschoolAssessmentCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        if ($assessmentCategories === []) {
            $assessmentCategories = ['Development', 'Behavior'];
        }

        return [
            'academic' => [
                'activeAcademicYear' => $activeAcademicYear ? $this->resolveAcademicYear($activeAcademicYear) : '',
                'activeAcademicYearDateRange' => $this->formatDateRange($activeAcademicYear),
                'activeTerm' => $activeTerm ? $this->resolveTermName($activeTerm) : '',
                'activeTermDateRange' => $this->formatDateRange($activeTerm),
                'academicStatus' => $activeAcademicYear?->status ?? '',
                'isConfigured' => (bool) $activeAcademicYear,
            ],
            'attendance' => [
                'currentAttendanceRules' => sprintf(
                    'Late after %d minutes; half day after %d minutes',
                    $attendanceService->getLateThreshold($attendanceSettings),
                    $attendanceService->getHalfDayThreshold($attendanceSettings),
                ),
                'late_threshold_minutes' => $attendanceSummary['late_threshold_minutes'],
                'absence_alert_days' => $attendanceSummary['absence_alert_days'],
                'school_days_per_week' => $attendanceSummary['school_days_per_week'],
                'calendar_events_count' => $attendanceSummary['calendar_events_count'],
                'school_week' => $attendanceSummary['school_week'],
                'lastUpdated' => $attendanceSettings->updated_at?->toIso8601String() ?? $record?->updated_at?->toIso8601String(),
                'isConfigured' => true,
            ],
            'payments' => [
                'currency' => $this->resolvePaymentCurrency($snapshot),
                'invoicePrefix' => $this->resolvePaymentPrefix($snapshot, 'invoice', 'INV'),
                'receiptPrefix' => $this->resolvePaymentPrefix($snapshot, 'receipt', 'REC'),
                'isConfigured' => true,
            ],
            'assessments' => [
                'activeGradingScale' => $this->resolveAssessmentScale($snapshot),
                'assessmentCategories' => $assessmentCategories,
                'assessmentCategoriesCount' => count($assessmentCategories),
                'isConfigured' => true,
            ],
            'health' => [
                'alertSeverityLevels' => ['Critical', 'High', 'Medium'],
                'healthCategories' => ['Allergies', 'Vaccinations', 'Medication'],
                'isConfigured' => true,
            ],
            'preferences' => [
                'organizationName' => (string) config('app.name', 'HFCCF'),
                'language' => app()->getLocale() === 'kh' ? 'Khmer' : 'English',
                'brandingStatus' => config('app.name') ? 'Configured' : 'Not configured',
                'isConfigured' => true,
            ],
        ];
    }

    private function resolveAcademicYear(PreschoolAcademicYear $academicYear): string
    {
        return trim((string) ($academicYear->label ?: $academicYear->code)) ?: 'Academic Year';
    }

    private function resolvePaymentCurrency(array $snapshot): string
    {
        return trim((string) data_get($snapshot, 'payment.currency', 'USD')) ?: 'USD';
    }

    private function resolvePaymentPrefix(array $snapshot, string $key, string $fallback): string
    {
        return trim((string) data_get($snapshot, "payment.{$key}Prefix", $fallback)) ?: $fallback;
    }

    private function resolveAssessmentScale(array $snapshot): string
    {
        return trim((string) data_get($snapshot, 'assessment.defaultTemplate', 'A-F')) ?: 'A-F';
    }

    private function resolveTermName(PreschoolAcademicTerm $term): string
    {
        return trim((string) ($term->name ?: $term->code)) ?: 'Term';
    }

    private function formatDateRange(PreschoolAcademicYear|PreschoolAcademicTerm|null $record): string
    {
        if (! $record) {
            return '';
        }

        $start = $record->start_date?->toDateString() ?? '';
        $end = $record->end_date?->toDateString() ?? '';

        if ($start === '' && $end === '') {
            return '';
        }

        return trim(($start !== '' ? $start : '—').' - '.($end !== '' ? $end : '—'));
    }
}
