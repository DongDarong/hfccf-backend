<?php

namespace App\Support;

use App\Models\PreschoolSettingsBackbone;
use App\Models\User;
use App\Services\AuditLogService;

class PreschoolSettingsBackboneService
{
    public const BACKBONE_KEY = 'academic-backbone';

    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

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
            'payment' => [
                'defaultTuitionFee' => 120,
                'paymentCycle' => 'monthly',
                'dueDay' => 5,
                'lateFeeRule' => 'fixed',
                'enableOverdueReminders' => true,
            ],
            'health' => [
                'criticalAlertsEnabled' => true,
                'guardianNotifications' => true,
                'teacherNotifications' => true,
                'adminNotifications' => true,
                'medicationReminders' => true,
                'vaccinationReminders' => true,
                'overdueVaccinationAlertDays' => 7,
                'medicationReminderMinutesBefore' => 30,
                'severityLevels' => [
                    ['key' => 'critical', 'label' => 'Critical', 'status' => 'active', 'type' => 'severity'],
                    ['key' => 'high', 'label' => 'High', 'status' => 'active', 'type' => 'severity'],
                    ['key' => 'medium', 'label' => 'Medium', 'status' => 'active', 'type' => 'severity'],
                ],
            ],
            'preferences' => [
                'timezone' => 'Asia/Phnom_Penh',
                'defaultLanguage' => 'en',
                'dateFormat' => 'DD/MM/YYYY',
                'timeFormat' => 'HH:mm',
                'minimumEnrollmentAgeMonths' => 24,
                'maximumEnrollmentAgeMonths' => 60,
                'autoApproveEnrollment' => false,
                'studentCodePrefix' => 'PS',
                'studentCodeYearFormat' => 'YYYY',
                'studentCodeSequenceLength' => 4,
                'defaultClassCapacity' => 18,
                'teacherStudentRatio' => 10,
                'waitlistEnabled' => true,
                'minimumGuardians' => 1,
                'maximumGuardians' => 2,
                'primaryGuardianRequired' => true,
                'pickupAuthorizationRequired' => true,
                'attendanceAlertEnabled' => true,
                'assessmentAlertEnabled' => true,
                'healthAlertEnabled' => true,
                'enrollmentNotificationEnabled' => true,
            ],
            'metadata' => [
                'version' => 1,
                'source' => 'defaults',
                'lastUpdatedAt' => null,
                'lastUpdatedBy' => null,
                'context' => [],
            ],
        ];
    }

    public function groupDefinitions(): array
    {
        return [
            'academic' => [
                'label' => 'Academic',
                'status' => 'active',
                'fields' => [
                    'academicYear' => ['type' => 'object', 'default' => $this->defaultSnapshot()['academicYear'], 'status' => 'active'],
                    'terms' => ['type' => 'array', 'default' => $this->defaultSnapshot()['terms'], 'status' => 'active'],
                    'classConfigurations' => ['type' => 'array', 'default' => $this->defaultSnapshot()['classConfigurations'], 'status' => 'active'],
                ],
            ],
            'attendance' => [
                'label' => 'Attendance',
                'status' => 'active',
                'fields' => [
                    'attendance' => ['type' => 'object', 'default' => $this->defaultSnapshot()['attendance'], 'status' => 'active'],
                ],
            ],
            'assessment' => [
                'label' => 'Assessment',
                'status' => 'active',
                'fields' => [
                    'assessment' => ['type' => 'object', 'default' => $this->defaultSnapshot()['assessment'], 'status' => 'active'],
                ],
            ],
            'health' => [
                'label' => 'Health',
                'status' => 'active',
                'fields' => [
                    'health' => ['type' => 'object', 'default' => $this->defaultSnapshot()['health'], 'status' => 'active'],
                ],
            ],
            'payment' => [
                'label' => 'Payment',
                'status' => 'active',
                'fields' => [
                    'payment' => ['type' => 'object', 'default' => $this->defaultSnapshot()['payment'], 'status' => 'active'],
                ],
            ],
            'preferences' => [
                'label' => 'Preferences',
                'status' => 'active',
                'fields' => [
                    'schedule' => ['type' => 'object', 'default' => $this->defaultSnapshot()['schedule'], 'status' => 'active'],
                    'enrollment' => ['type' => 'object', 'default' => $this->defaultSnapshot()['enrollment'], 'status' => 'active'],
                ],
            ],
        ];
    }

    public function snapshot(): array
    {
        $record = PreschoolSettingsBackbone::query()
            ->where('key', self::BACKBONE_KEY)
            ->first();

        $payload = is_array($record?->payload) ? $record->payload : [];
        $snapshot = array_replace_recursive($this->defaultSnapshot(), $payload);
        $snapshot['groups'] = $this->buildGroups($snapshot);
        $snapshot['metadata'] = $this->buildMetadata($snapshot['metadata'] ?? []);

        return $snapshot;
    }

    public function saveSnapshot(array $payload, ?User $actor = null, array $context = []): array
    {
        $current = $this->snapshot();
        $snapshot = array_replace_recursive($current, $payload);
        $snapshot['groups'] = $this->buildGroups($snapshot);
        $snapshot['metadata'] = $this->buildMetadata(array_replace_recursive($snapshot['metadata'] ?? [], [
            'source' => $context['source'] ?? 'preschool-settings-backbone',
            'lastUpdatedAt' => now()->toISOString(),
            'lastUpdatedBy' => $actor?->id,
            'context' => array_replace_recursive(
                is_array(data_get($snapshot, 'metadata.context')) ? data_get($snapshot, 'metadata.context') : [],
                is_array($context['context'] ?? null) ? $context['context'] : [],
            ),
        ]));

        PreschoolSettingsBackbone::query()->updateOrCreate(
            ['key' => self::BACKBONE_KEY],
            [
                'payload' => $snapshot,
                'is_active' => true,
                'is_default' => true,
            ],
        );

        $this->recordAuditTrail($current, $snapshot, $actor, $context);

        return $snapshot;
    }

    public function currentAcademicContext(): array
    {
        $snapshot = $this->snapshot();
        $academicYear = trim((string) data_get($snapshot, 'academicYear.currentAcademicYear', '2025 - 2026'));
        $terms = array_values(array_filter((array) data_get($snapshot, 'terms', [])));
        $activeTerm = null;

        foreach ($terms as $term) {
            if ((string) data_get($term, 'status', '') === 'active') {
                $activeTerm = $term;
                break;
            }
        }

        if (! $activeTerm) {
            $activeTerm = $terms[0] ?? null;
        }

        return [
            'academic_year' => $academicYear !== '' ? $academicYear : '2025 - 2026',
            'term_label' => trim((string) data_get($activeTerm, 'name', 'Term 1')) ?: 'Term 1',
        ];
    }

    private function buildGroups(array $snapshot): array
    {
        return [
            'academic' => [
                'key' => 'academic',
                'label' => 'Academic',
                'status' => 'active',
                'valueType' => 'group',
                'settings' => [
                    'academicYear' => $snapshot['academicYear'] ?? [],
                    'terms' => $snapshot['terms'] ?? [],
                    'classConfigurations' => $snapshot['classConfigurations'] ?? [],
                ],
                'schema' => $this->groupDefinitions()['academic']['fields'],
            ],
            'attendance' => [
                'key' => 'attendance',
                'label' => 'Attendance',
                'status' => 'active',
                'valueType' => 'group',
                'settings' => $snapshot['attendance'] ?? [],
                'schema' => $this->groupDefinitions()['attendance']['fields'],
            ],
            'assessment' => [
                'key' => 'assessment',
                'label' => 'Assessment',
                'status' => 'active',
                'valueType' => 'group',
                'settings' => $snapshot['assessment'] ?? [],
                'schema' => $this->groupDefinitions()['assessment']['fields'],
            ],
            'health' => [
                'key' => 'health',
                'label' => 'Health',
                'status' => 'active',
                'valueType' => 'group',
                'settings' => $snapshot['health'] ?? [],
                'schema' => $this->groupDefinitions()['health']['fields'],
            ],
            'payment' => [
                'key' => 'payment',
                'label' => 'Payment',
                'status' => 'active',
                'valueType' => 'group',
                'settings' => $snapshot['payment'] ?? [],
                'schema' => $this->groupDefinitions()['payment']['fields'],
            ],
            'preferences' => [
                'key' => 'preferences',
                'label' => 'Preferences',
                'status' => 'active',
                'valueType' => 'group',
                'settings' => [
                    'schedule' => $snapshot['schedule'] ?? [],
                    'enrollment' => $snapshot['enrollment'] ?? [],
                ],
                'schema' => $this->groupDefinitions()['preferences']['fields'],
            ],
        ];
    }

    private function buildMetadata(array $metadata): array
    {
        return array_replace_recursive([
            'version' => 1,
            'source' => 'defaults',
            'lastUpdatedAt' => null,
            'lastUpdatedBy' => null,
            'context' => [],
        ], $metadata);
    }

    private function recordAuditTrail(array $before, array $after, ?User $actor, array $context = []): void
    {
        $changes = $this->diffSnapshot($before, $after);

        foreach ($changes as $change) {
            $this->auditLogService->recordSafely([
                'actor_user_id' => $actor?->id,
                'domain' => 'preschool',
                'action' => 'settings.updated',
                'entity_type' => 'preschool_settings_backbone',
                'entity_id' => self::BACKBONE_KEY,
                'entity_label' => sprintf('%s.%s', $change['group'], $change['path']),
                'old_values' => ['value' => $change['old']],
                'new_values' => ['value' => $change['new']],
                'metadata' => [
                    'group' => $change['group'],
                    'setting' => $change['path'],
                    'source' => $context['source'] ?? 'preschool-settings-backbone',
                    'context' => $context['context'] ?? [],
                ],
                'ip_address' => $context['ip_address'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'created_at' => now(),
            ]);
        }
    }

    private function diffSnapshot(array $before, array $after): array
    {
        $paths = [
            'academic' => ['academicYear', 'terms', 'classConfigurations'],
            'attendance' => ['attendance'],
            'assessment' => ['assessment'],
            'health' => ['health'],
            'payment' => ['payment'],
            'preferences' => ['schedule', 'enrollment'],
        ];

        $changes = [];

        foreach ($paths as $group => $groupPaths) {
            foreach ($groupPaths as $path) {
                $beforeValue = data_get($before, $path);
                $afterValue = data_get($after, $path);

                if ($beforeValue === $afterValue) {
                    continue;
                }

                $changes[] = [
                    'group' => $group,
                    'path' => $path,
                    'old' => $beforeValue,
                    'new' => $afterValue,
                ];
            }
        }

        return $changes;
    }
}
