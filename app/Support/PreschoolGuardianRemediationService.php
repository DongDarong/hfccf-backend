<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianRemediationLog;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianRemediationService
{
    public function __construct(
        private readonly PreschoolGuardianRemediationAuditService $audit,
        private readonly PreschoolGuardianSnapshotService $snapshot,
    ) {}

    /**
     * Mark an issue as reviewed without changing any data.
     * Safe for issues where no direct fix is possible (e.g. pickup_permission_issue).
     */
    public function markIssueReviewed(User $user, array $payload): array
    {
        $this->ensureAdminAccess($user);

        $log = DB::transaction(function () use ($user, $payload): PreschoolGuardianRemediationLog {
            return $this->audit->log(
                issueType: $payload['issue_type'],
                action: 'mark_reviewed',
                performedBy: $user,
                options: [
                    'issue_key' => $payload['issue_key'] ?? null,
                    'student_id' => $payload['student_id'] ?? null,
                    'guardian_id' => $payload['guardian_id'] ?? null,
                    'relationship_id' => $payload['relationship_id'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ],
            );
        });

        return $this->successResponse('Issue marked as reviewed.', $log->toArray());
    }

    /**
     * Set one relationship as primary for a student, clearing all others.
     * Resolves multiple_active_primary_guardians.
     */
    public function setPrimaryGuardian(User $user, int $studentId, int $relationshipId, ?string $notes): array
    {
        $this->ensureAdminAccess($user);

        $student = PreschoolStudent::query()->findOrFail($studentId);
        $target = PreschoolStudentGuardian::query()
            ->where('id', $relationshipId)
            ->where('student_id', $studentId)
            ->firstOrFail();

        abort_if(
            $target->status !== PreschoolGuardianStatus::ACTIVE,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Only an active relationship can be set as primary.',
        );

        $beforeAll = $student->studentGuardians()
            ->where('is_primary', true)
            ->get()
            ->map(fn ($r) => $this->audit->relationshipSnapshot($r))
            ->all();

        DB::transaction(function () use ($user, $student, $target, $notes, $beforeAll): void {
            $student->studentGuardians()
                ->where('id', '!=', $target->id)
                ->where('is_primary', true)
                ->update([
                    'is_primary' => false,
                    'updated_by_user_id' => $user->id,
                ]);

            $target->update([
                'is_primary' => true,
                'updated_by_user_id' => $user->id,
            ]);

            $afterAll = $student->fresh()->studentGuardians()
                ->where('is_primary', true)
                ->get()
                ->map(fn ($r) => $this->audit->relationshipSnapshot($r))
                ->all();

            $this->audit->log(
                issueType: 'multiple_active_primary_guardians',
                action: 'set_primary',
                performedBy: $user,
                options: [
                    'student_id' => $student->id,
                    'guardian_id' => $target->guardian_id,
                    'relationship_id' => $target->id,
                    'before_snapshot' => ['primaryRelationships' => $beforeAll],
                    'after_snapshot' => ['primaryRelationships' => $afterAll],
                    'notes' => $notes,
                ],
            );
        });

        $target->refresh();

        return $this->successResponse(
            'Primary guardian set. Other primary flags cleared.',
            $this->audit->relationshipSnapshot($target),
        );
    }

    /**
     * Clear the is_primary flag from an archived relationship.
     * Resolves archived_primary_relationship.
     */
    public function clearInvalidPrimary(User $user, int $relationshipId, ?string $notes): array
    {
        $this->ensureAdminAccess($user);

        $rel = PreschoolStudentGuardian::query()->findOrFail($relationshipId);

        abort_if(
            $rel->status !== PreschoolGuardianStatus::ARCHIVED,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'This action only applies to archived relationships.',
        );

        abort_unless($rel->is_primary, Response::HTTP_UNPROCESSABLE_ENTITY, 'Relationship is already not primary.');

        $before = $this->audit->relationshipSnapshot($rel);

        DB::transaction(function () use ($user, $rel, $notes, $before): void {
            $rel->update([
                'is_primary' => false,
                'updated_by_user_id' => $user->id,
            ]);

            $after = $this->audit->relationshipSnapshot($rel->fresh());

            $this->audit->log(
                issueType: 'archived_primary_relationship',
                action: 'clear_invalid_primary',
                performedBy: $user,
                options: [
                    'student_id' => $rel->student_id,
                    'guardian_id' => $rel->guardian_id,
                    'relationship_id' => $rel->id,
                    'before_snapshot' => $before,
                    'after_snapshot' => $after,
                    'notes' => $notes,
                ],
            );
        });

        return $this->successResponse(
            'Invalid primary flag cleared from archived relationship.',
            $this->audit->relationshipSnapshot($rel->fresh()),
        );
    }

    /**
     * Clear is_primary, can_pickup, and emergency_priority from an inactive relationship.
     * Resolves inactive_emergency_contact.
     */
    public function clearInvalidEmergencyContact(User $user, int $relationshipId, ?string $notes): array
    {
        $this->ensureAdminAccess($user);

        $rel = PreschoolStudentGuardian::query()->findOrFail($relationshipId);

        abort_if(
            $rel->status !== PreschoolGuardianStatus::INACTIVE,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'This action only applies to inactive relationships.',
        );

        $hasFlags = $rel->is_primary || $rel->can_pickup || $rel->emergency_priority !== null;

        abort_unless($hasFlags, Response::HTTP_UNPROCESSABLE_ENTITY, 'No active contact flags found on this relationship.');

        $before = $this->audit->relationshipSnapshot($rel);

        DB::transaction(function () use ($user, $rel, $notes, $before): void {
            $rel->update([
                'is_primary' => false,
                'can_pickup' => false,
                'emergency_priority' => null,
                'updated_by_user_id' => $user->id,
            ]);

            $after = $this->audit->relationshipSnapshot($rel->fresh());

            $this->audit->log(
                issueType: 'inactive_emergency_contact',
                action: 'clear_invalid_emergency_contact',
                performedBy: $user,
                options: [
                    'student_id' => $rel->student_id,
                    'guardian_id' => $rel->guardian_id,
                    'relationship_id' => $rel->id,
                    'before_snapshot' => $before,
                    'after_snapshot' => $after,
                    'notes' => $notes,
                ],
            );
        });

        return $this->successResponse(
            'Invalid contact flags cleared from inactive relationship.',
            $this->audit->relationshipSnapshot($rel->fresh()),
        );
    }

    /**
     * Overwrite the student's legacy guardian_name and guardian_phone
     * using the normalized primary guardian snapshot.
     * Requires explicit confirmation from the caller. Never auto-runs.
     */
    public function reconcileLegacyFields(User $user, int $studentId, ?string $notes): array
    {
        $this->ensureAdminAccess($user);

        $student = PreschoolStudent::query()
            ->with(['studentGuardians.guardian'])
            ->findOrFail($studentId);

        $preferred = $this->snapshot->preferredGuardianSnapshot($student);

        abort_if(
            ($preferred['source'] ?? '') !== 'normalized',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'No normalized guardian relationship found for this student. Cannot reconcile legacy fields.',
        );

        $mismatch = $this->snapshot->legacyMismatch($student);

        abort_if($mismatch === [], Response::HTTP_UNPROCESSABLE_ENTITY, 'Legacy fields already match the normalized guardian data. No reconciliation needed.');

        $before = $this->audit->studentSnapshot($student);

        DB::transaction(function () use ($user, $student, $preferred, $notes, $before): void {
            $student->update([
                'guardian_name' => $preferred['guardianName'],
                'guardian_phone' => $preferred['guardianPhone'],
            ]);

            $after = $this->audit->studentSnapshot($student->fresh());

            $this->audit->log(
                issueType: 'legacy_guardian_mismatch',
                action: 'reconcile_legacy_fields',
                performedBy: $user,
                options: [
                    'student_id' => $student->id,
                    'guardian_id' => $preferred['guardianId'],
                    'relationship_id' => $preferred['relationshipId'],
                    'before_snapshot' => $before,
                    'after_snapshot' => $after,
                    'notes' => $notes,
                ],
            );
        });

        return $this->successResponse(
            'Legacy guardian fields reconciled from normalized data.',
            $this->audit->studentSnapshot($student->fresh()),
        );
    }

    /**
     * Archive a specific student-guardian relationship for a duplicate candidate.
     * Does NOT merge, delete, or modify the guardian record itself.
     */
    public function archiveDuplicateCandidate(User $user, int $relationshipId, ?string $notes): array
    {
        $this->ensureAdminAccess($user);

        $rel = PreschoolStudentGuardian::query()
            ->with('guardian')
            ->findOrFail($relationshipId);

        abort_if(
            $rel->status === PreschoolGuardianStatus::ARCHIVED,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Relationship is already archived.',
        );

        $before = $this->audit->relationshipSnapshot($rel);

        DB::transaction(function () use ($user, $rel, $notes, $before): void {
            $rel->update([
                'status' => PreschoolGuardianStatus::ARCHIVED,
                'is_primary' => false,
                'can_pickup' => false,
                'emergency_priority' => null,
                'ends_at' => now()->toDateString(),
                'updated_by_user_id' => $user->id,
            ]);

            $after = $this->audit->relationshipSnapshot($rel->fresh());

            $this->audit->log(
                issueType: 'duplicate_guardian_candidate',
                action: 'archive_duplicate_candidate',
                performedBy: $user,
                options: [
                    'student_id' => $rel->student_id,
                    'guardian_id' => $rel->guardian_id,
                    'relationship_id' => $rel->id,
                    'before_snapshot' => $before,
                    'after_snapshot' => $after,
                    'notes' => $notes,
                ],
            );
        });

        return $this->successResponse(
            'Duplicate candidate relationship archived. Guardian record preserved.',
            $this->audit->relationshipSnapshot($rel->fresh()),
        );
    }

    /**
     * Archive a guardian record that has no student relationships.
     * Requires explicit confirmation. Refuses if any relationships exist.
     */
    public function archiveOrphanGuardian(User $user, int $guardianId, ?string $notes): array
    {
        $this->ensureAdminAccess($user);

        $guardian = PreschoolGuardian::query()
            ->withCount(['studentGuardians as total_relationships_count'])
            ->findOrFail($guardianId);

        abort_if(
            $guardian->total_relationships_count > 0,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Guardian still has student relationships and cannot be archived as orphan. Use the relationship archive flow instead.',
        );

        abort_if(
            $guardian->status === PreschoolGuardianStatus::ARCHIVED,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Guardian is already archived.',
        );

        $before = $this->audit->guardianSnapshot($guardian);

        DB::transaction(function () use ($user, $guardian, $notes, $before): void {
            $guardian->update([
                'status' => PreschoolGuardianStatus::ARCHIVED,
                'updated_by_user_id' => $user->id,
            ]);

            $after = $this->audit->guardianSnapshot($guardian->fresh());

            $this->audit->log(
                issueType: 'guardian_without_students',
                action: 'archive_orphan_guardian',
                performedBy: $user,
                options: [
                    'guardian_id' => $guardian->id,
                    'before_snapshot' => $before,
                    'after_snapshot' => $after,
                    'notes' => $notes,
                ],
            );
        });

        return $this->successResponse(
            'Orphan guardian archived. No relationships were affected.',
            $this->audit->guardianSnapshot($guardian->fresh()),
        );
    }

    private function successResponse(string $message, mixed $data): array
    {
        return ['success' => true, 'message' => $message, 'data' => $data];
    }

    private function ensureAdminAccess(User $user): void
    {
        abort_unless(
            in_array($user->role_code, ['superadmin', 'adminpreschool'], true),
            Response::HTTP_FORBIDDEN,
            'Forbidden.',
        );
    }
}
