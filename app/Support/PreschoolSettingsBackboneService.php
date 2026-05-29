<?php

namespace App\Support;

use App\Models\PreschoolSettingsBackbone;

class PreschoolSettingsBackboneService
{
    public const BACKBONE_KEY = 'academic-backbone';

    public function defaultSnapshot(): array
    {
        return [
            'academicYear' => [
                'currentAcademicYear' => '2025 - 2026',
                'startDate' => '2025-07-01',
                'endDate' => '2026-04-30',
                'status' => 'active',
            ],
            'terms' => [
                [
                    'id' => 'term-1',
                    'name' => 'Term 1',
                    'startDate' => '2025-07-01',
                    'endDate' => '2025-10-30',
                    'status' => 'active',
                ],
                [
                    'id' => 'term-2',
                    'name' => 'Term 2',
                    'startDate' => '2025-11-01',
                    'endDate' => '2026-02-28',
                    'status' => 'inactive',
                ],
            ],
            'classConfigurations' => [
                [
                    'id' => 'class-1',
                    'classLevel' => 'nursery',
                    'capacity' => 18,
                    'assignedTeacher' => 'lead-teacher',
                    'room' => 'Room A',
                    'status' => 'active',
                ],
                [
                    'id' => 'class-2',
                    'classLevel' => 'kindergarten-1',
                    'capacity' => 20,
                    'assignedTeacher' => 'assistant-teacher',
                    'room' => 'Room B',
                    'status' => 'inactive',
                ],
            ],
            'attendance' => [
                'markingWindow' => '07:30 - 08:15',
                'lateThreshold' => 15,
                'absenceRule' => 'window-and-threshold',
                'teacherCanEditAttendance' => true,
            ],
            'assessment' => [
                'assessmentCycle' => 'term',
                'finalizationMode' => 'publish-only',
                'defaultTemplate' => 'PRESCHOOL-DEVELOPMENT-CORE',
                'requireTeacherNotes' => true,
            ],
            'schedule' => [
                'weeklyMode' => 'five-day',
                'defaultSlotMinutes' => 45,
                'planningWindow' => 'weekly',
                'allowTeacherOverrides' => false,
            ],
            'enrollment' => [
                'enrollmentCycle' => 'term',
                'defaultClassLevel' => 'nursery',
                'transferPolicy' => 'admin-only',
                'capacityReviewMode' => 'manual',
            ],
        ];
    }

    public function snapshot(): array
    {
        $record = PreschoolSettingsBackbone::query()
            ->where('key', self::BACKBONE_KEY)
            ->first();

        $payload = is_array($record?->payload) ? $record->payload : [];

        return array_replace_recursive($this->defaultSnapshot(), $payload);
    }

    public function saveSnapshot(array $payload): array
    {
        $snapshot = array_replace_recursive($this->snapshot(), $payload);

        PreschoolSettingsBackbone::query()->updateOrCreate(
            ['key' => self::BACKBONE_KEY],
            [
                'payload' => $snapshot,
                'is_active' => true,
                'is_default' => true,
            ],
        );

        return $snapshot;
    }

    public function currentAcademicContext(): array
    {
        $snapshot = $this->snapshot();
        $academicYear = trim((string) data_get($snapshot, 'academicYear.currentAcademicYear', '2025 - 2026'));
        $terms = collect(data_get($snapshot, 'terms', []));
        $activeTerm = $terms->firstWhere('status', 'active') ?: $terms->first();

        return [
            'academic_year' => $academicYear !== '' ? $academicYear : '2025 - 2026',
            'term_label' => trim((string) data_get($activeTerm, 'name', 'Term 1')) ?: 'Term 1',
        ];
    }
}
