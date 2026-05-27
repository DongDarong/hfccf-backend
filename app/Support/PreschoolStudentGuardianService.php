<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreschoolStudentGuardianService
{
    /**
     * Student-guardian links stay transactional so we can preserve history while
     * enforcing the single-primary rule for the active contact set.
     */
    public function listStudentGuardians(User $user, PreschoolStudent $student): Collection
    {
        app(PreschoolGuardianService::class)->ensureUserCanAccessStudent($user, $student);

        return PreschoolStudentGuardian::query()
            ->with(['guardian'])
            ->where('student_id', $student->id)
            ->orderByRaw("CASE WHEN status = '".PreschoolGuardianStatus::ACTIVE."' THEN 0 WHEN status = '".PreschoolGuardianStatus::INACTIVE."' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN is_primary = 1 THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(emergency_priority, 999999) ASC')
            ->orderBy('created_at')
            ->get();
    }

    public function linkGuardian(User $user, PreschoolStudent $student, array $data): PreschoolStudentGuardian
    {
        $this->ensureAdminAccess($user);

        $guardian = PreschoolGuardian::query()->findOrFail($data['guardian_id']);
        $this->ensureGuardianLinkable($guardian);
        $status = trim((string) ($data['status'] ?? '')) ?: PreschoolGuardianStatus::ACTIVE;

        if (PreschoolStudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->where('status', PreschoolGuardianStatus::ACTIVE)
            ->exists()) {
            throw ValidationException::withMessages([
                'guardian_id' => 'An active guardian relationship already exists for this student and guardian pair.',
            ]);
        }

        return DB::transaction(function () use ($user, $student, $guardian, $data, $status): PreschoolStudentGuardian {
            $isPrimary = (bool) ($data['is_primary'] ?? false);
            $canPickup = (bool) ($data['can_pickup'] ?? false);
            if ($status !== PreschoolGuardianStatus::ACTIVE) {
                $isPrimary = false;
                $canPickup = false;
            }

            $relationship = PreschoolStudentGuardian::query()->create([
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
                'relationship_type' => $data['relationship_type'],
                'is_primary' => $isPrimary,
                'can_pickup' => $canPickup,
                'emergency_priority' => $data['emergency_priority'] ?? null,
                'status' => $status,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);

            $this->syncPrimaryContact($user, $relationship);

            return $relationship->fresh(['guardian', 'student']);
        });
    }

    public function updateRelationshipForGuardian(User $user, PreschoolStudent $student, PreschoolGuardian $guardian, array $data): PreschoolStudentGuardian
    {
        $relationship = $this->findRelationship($student, $guardian);

        return $this->updateRelationship($user, $relationship, $data);
    }

    /**
     * Pair-based actions keep the student and guardian anchors visible in the
     * API, which reduces accidental updates to the wrong historical link.
     */
    public function setPrimaryRelationship(User $user, PreschoolStudent $student, PreschoolGuardian $guardian): PreschoolStudentGuardian
    {
        $relationship = $this->findRelationship($student, $guardian);
        $this->ensureGuardianLinkable($relationship->guardian()->firstOrFail());

        return DB::transaction(function () use ($user, $relationship): PreschoolStudentGuardian {
            $relationship->status = PreschoolGuardianStatus::ACTIVE;
            $relationship->is_primary = true;
            $relationship->updated_by_user_id = $user->id;
            $relationship->save();

            $this->syncPrimaryContact($user, $relationship);

            return $relationship->fresh(['guardian', 'student']);
        });
    }

    public function archiveRelationshipForGuardian(User $user, PreschoolStudent $student, PreschoolGuardian $guardian): PreschoolStudentGuardian
    {
        $relationship = $this->findRelationship($student, $guardian);

        return $this->archiveRelationship($user, $relationship);
    }

    public function restoreRelationshipForGuardian(User $user, PreschoolStudent $student, PreschoolGuardian $guardian): PreschoolStudentGuardian
    {
        $relationship = $this->findRelationship($student, $guardian);
        $this->ensureGuardianLinkable($relationship->guardian()->firstOrFail());

        return DB::transaction(function () use ($user, $relationship): PreschoolStudentGuardian {
            $relationship->status = PreschoolGuardianStatus::ACTIVE;
            $relationship->ends_at = null;
            $relationship->updated_by_user_id = $user->id;
            $relationship->save();

            return $relationship->fresh(['guardian', 'student']);
        });
    }

    public function updateRelationship(User $user, PreschoolStudentGuardian $relationship, array $data): PreschoolStudentGuardian
    {
        $this->ensureAdminAccess($user);

        return DB::transaction(function () use ($user, $relationship, $data): PreschoolStudentGuardian {
            $guardian = $relationship->guardian()->firstOrFail();
            $this->ensureGuardianLinkable($guardian);

            foreach (['relationship_type', 'status', 'starts_at', 'ends_at', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $relationship->{$field} = $field === 'status'
                        ? (trim((string) $data[$field]) ?: PreschoolGuardianStatus::ACTIVE)
                        : $data[$field];
                }
            }

            if (array_key_exists('is_primary', $data)) {
                $relationship->is_primary = (bool) $data['is_primary'];
            }

            if (array_key_exists('can_pickup', $data)) {
                $relationship->can_pickup = (bool) $data['can_pickup'];
            }

            if (array_key_exists('emergency_priority', $data)) {
                $relationship->emergency_priority = $data['emergency_priority'];
            }

            if (in_array($relationship->status, [PreschoolGuardianStatus::INACTIVE, PreschoolGuardianStatus::ARCHIVED], true)) {
                $relationship->is_primary = false;
                $relationship->can_pickup = false;
            }

            $relationship->updated_by_user_id = $user->id;
            $relationship->save();

            $this->syncPrimaryContact($user, $relationship);

            return $relationship->fresh(['guardian', 'student']);
        });
    }

    public function archiveRelationship(User $user, PreschoolStudentGuardian $relationship): PreschoolStudentGuardian
    {
        $this->ensureAdminAccess($user);

        return DB::transaction(function () use ($user, $relationship): PreschoolStudentGuardian {
            $relationship->status = PreschoolGuardianStatus::ARCHIVED;
            $relationship->is_primary = false;
            $relationship->can_pickup = false;
            $relationship->ends_at = $relationship->ends_at ?? now()->toDateString();
            $relationship->updated_by_user_id = $user->id;
            $relationship->save();

            return $relationship->fresh(['guardian', 'student']);
        });
    }

    private function syncPrimaryContact(User $user, PreschoolStudentGuardian $relationship): void
    {
        if (! $relationship->exists || ! $relationship->is_primary || $relationship->status !== PreschoolGuardianStatus::ACTIVE) {
            return;
        }

        PreschoolStudentGuardian::query()
            ->where('student_id', $relationship->student_id)
            ->where('id', '!=', $relationship->id)
            ->where('status', PreschoolGuardianStatus::ACTIVE)
            ->where('is_primary', true)
            ->update([
                'is_primary' => false,
                'updated_by_user_id' => $user->id,
                'updated_at' => now(),
            ]);
    }

    private function ensureGuardianLinkable(PreschoolGuardian $guardian): void
    {
        if ($guardian->status === PreschoolGuardianStatus::ARCHIVED) {
            throw ValidationException::withMessages([
                'guardian_id' => 'Archived guardians cannot be linked to active student relationships.',
            ]);
        }
    }

    private function findRelationship(PreschoolStudent $student, PreschoolGuardian $guardian): PreschoolStudentGuardian
    {
        return PreschoolStudentGuardian::query()
            ->with(['guardian', 'student'])
            ->where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->firstOrFail();
    }

    private function ensureAdminAccess(?User $user): void
    {
        abort_unless($user, 401, 'Unauthenticated.');
        abort_unless(in_array($user->role_code, ['superadmin', 'adminpreschool'], true), 403, 'Forbidden.');
    }
}
