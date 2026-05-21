<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianConsistencyService
{
    public function __construct(
        private readonly PreschoolGuardianSnapshotService $snapshot,
    ) {}

    /**
     * Consistency checks stay read-only and staff-facing so we can spot drift
     * between normalized relationships and legacy compatibility fields early.
     */
    public function report(User $user): array
    {
        $this->ensureAdminAccess($user);

        $students = PreschoolStudent::query()
            ->with(['studentGuardians.guardian', 'classes'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $guardians = PreschoolGuardian::query()
            ->with(['studentGuardians'])
            ->withCount([
                'studentGuardians as relationships_count',
                'activeStudentGuardians as active_relationships_count',
            ])
            ->orderBy('full_name')
            ->orderBy('id')
            ->get();

        $issues = collect();

        foreach ($students as $student) {
            $activeRelationships = $this->snapshot->activeRelationships($student);
            $primaryCount = $activeRelationships->where('is_primary', true)->count();

            if ($activeRelationships->isEmpty()) {
                $issues->push($this->issue(
                    'student_no_active_guardian',
                    'critical',
                    'Student has no active guardian',
                    'This student does not currently have an active guardian relationship.',
                    ['student' => $this->studentSnapshot($student)],
                ));
            }

            if ($primaryCount > 1) {
                $issues->push($this->issue(
                    'multiple_active_primary_guardians',
                    'critical',
                    'Student has multiple primary guardians',
                    'Only one active primary guardian should exist for a student.',
                    [
                        'student' => $this->studentSnapshot($student),
                        'relationships' => $activeRelationships->where('is_primary', true)->map(fn (PreschoolStudentGuardian $relationship): array => $this->relationshipSnapshot($relationship))->values()->all(),
                    ],
                ));
            }

            foreach ($student->studentGuardians as $relationship) {
                if ($relationship->status === PreschoolGuardianStatus::ARCHIVED && $relationship->is_primary) {
                    $issues->push($this->issue(
                        'archived_primary_relationship',
                        'warning',
                        'Archived relationship is still marked primary',
                        'Primary flags should be cleared when a relationship is archived.',
                        [
                            'student' => $this->studentSnapshot($student),
                            'relationship' => $this->relationshipSnapshot($relationship),
                        ],
                    ));
                }

                if ($relationship->status === PreschoolGuardianStatus::INACTIVE && (
                    $relationship->is_primary || $relationship->can_pickup || $relationship->emergency_priority !== null
                )) {
                    $issues->push($this->issue(
                        'inactive_emergency_contact',
                        'warning',
                        'Inactive relationship still has active contact flags',
                        'Inactive relationships should not remain primary, pickup-allowed, or emergency contacts.',
                        [
                            'student' => $this->studentSnapshot($student),
                            'relationship' => $this->relationshipSnapshot($relationship),
                        ],
                    ));
                }

                if (
                    $relationship->status === PreschoolGuardianStatus::ACTIVE
                    && $relationship->emergency_priority !== null
                    && ! $relationship->can_pickup
                ) {
                    $issues->push($this->issue(
                        'pickup_permission_issue',
                        'warning',
                        'Emergency contact cannot pick up the student',
                        'Emergency contacts should have explicit pickup permission when the relationship is active.',
                        [
                            'student' => $this->studentSnapshot($student),
                            'relationship' => $this->relationshipSnapshot($relationship),
                        ],
                    ));
                }
            }

            $legacyMismatch = $this->snapshot->legacyMismatch($student);
            if ($legacyMismatch !== []) {
                $issues->push($this->issue(
                    'legacy_guardian_mismatch',
                    'warning',
                    'Legacy guardian fields differ from normalized guardian data',
                    'The compatibility fields on the student row should stay in sync with the normalized primary guardian snapshot.',
                    [
                        'student' => $this->studentSnapshot($student),
                        'difference' => $legacyMismatch,
                        'preferredGuardian' => $this->snapshot->preferredGuardianSnapshot($student),
                    ],
                ));
            }
        }

        foreach ($guardians as $guardian) {
            if ($guardian->relationships_count === 0) {
                $issues->push($this->issue(
                    'guardian_without_students',
                    'info',
                    'Guardian is not linked to any students',
                    'This guardian record exists, but no student-guardian relationships are attached yet.',
                    [
                        'guardian' => $this->guardianSnapshot($guardian),
                    ],
                ));
            }
        }

        $items = $issues
            ->sortBy(static function (array $issue): int {
                return match ($issue['severity']) {
                    'critical' => 0,
                    'warning' => 1,
                    default => 2,
                };
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'studentsWithoutActiveGuardian' => $issues->where('type', 'student_no_active_guardian')->count(),
                'multiplePrimaryGuardianStudents' => $issues->where('type', 'multiple_active_primary_guardians')->count(),
                'guardiansWithoutStudents' => $issues->where('type', 'guardian_without_students')->count(),
                'pickupPermissionIssues' => $issues->where('type', 'pickup_permission_issue')->count(),
                'archivedPrimaryRelationships' => $issues->where('type', 'archived_primary_relationship')->count(),
                'inactiveEmergencyContacts' => $issues->where('type', 'inactive_emergency_contact')->count(),
                'legacyMismatches' => $issues->where('type', 'legacy_guardian_mismatch')->count(),
                'criticalCount' => $issues->where('severity', 'critical')->count(),
                'warningCount' => $issues->where('severity', 'warning')->count(),
                'infoCount' => $issues->where('severity', 'info')->count(),
                'issueCount' => $issues->count(),
            ],
            'issues' => $items,
            'generatedAt' => now()->toISOString(),
        ];
    }

    private function issue(string $type, string $severity, string $title, string $message, array $data): array
    {
        return [
            'id' => $type.'-'.md5(json_encode($data)),
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'student' => $data['student'] ?? null,
            'guardian' => $data['guardian'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'difference' => $data['difference'] ?? null,
            'preferredGuardian' => $data['preferredGuardian'] ?? null,
        ];
    }

    private function studentSnapshot(PreschoolStudent $student): array
    {
        $guardianSnapshot = $this->snapshot->preferredGuardianSnapshot($student);

        return [
            'id' => $student->id,
            'studentCode' => $student->student_code,
            'fullName' => trim($student->first_name.' '.$student->last_name),
            'status' => $student->status,
            // Prefer the normalized guardian snapshot so the compatibility
            // columns cannot override active guardian relationships.
            'guardianName' => $guardianSnapshot['guardianName'] ?? $student->guardian_name,
            'guardianPhone' => $guardianSnapshot['guardianPhone'] ?? $student->guardian_phone,
            'guardianSource' => $guardianSnapshot['source'] ?? 'legacy',
            'classesCount' => $student->classes->count(),
            'activeRelationshipCount' => $this->snapshot->activeRelationships($student)->count(),
        ];
    }

    private function guardianSnapshot(PreschoolGuardian $guardian): array
    {
        return [
            'id' => $guardian->id,
            'fullName' => $guardian->full_name,
            'phone' => $guardian->phone,
            'secondaryPhone' => $guardian->secondary_phone,
            'email' => $guardian->email,
            'status' => $guardian->status,
            'relationshipsCount' => (int) ($guardian->relationships_count ?? 0),
            'activeRelationshipsCount' => (int) ($guardian->active_relationships_count ?? 0),
        ];
    }

    private function relationshipSnapshot(PreschoolStudentGuardian $relationship): array
    {
        return [
            'id' => $relationship->id,
            'studentId' => $relationship->student_id,
            'guardianId' => $relationship->guardian_id,
            'relationshipType' => $relationship->relationship_type,
            'isPrimary' => (bool) $relationship->is_primary,
            'canPickup' => (bool) $relationship->can_pickup,
            'emergencyPriority' => $relationship->emergency_priority,
            'status' => $relationship->status,
            'startsAt' => $relationship->starts_at?->toDateString(),
            'endsAt' => $relationship->ends_at?->toDateString(),
            'notes' => $relationship->notes,
        ];
    }

    private function ensureAdminAccess(?User $user): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        abort_unless(in_array($user->role_code, ['superadmin', 'adminpreschool'], true), Response::HTTP_FORBIDDEN, 'Forbidden.');
    }
}
