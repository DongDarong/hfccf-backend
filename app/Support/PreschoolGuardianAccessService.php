<?php

namespace App\Support;

use App\Models\PreschoolGuardianPortalAccount;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianAccessService
{
    /**
     * Portal access is resolved through the portal account, not through the
     * guardian master record, so revocation can safely cut login access.
     */
    public function resolveActiveAccount(User $user): PreschoolGuardianPortalAccount
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        abort_unless($user->role_code === 'guardian', Response::HTTP_FORBIDDEN, 'Forbidden.');

        $account = PreschoolGuardianPortalAccount::query()
            ->with('guardian')
            ->where('user_id', $user->id)
            ->first();

        abort_unless($account && $account->status === PreschoolGuardianPortalStatus::ACTIVE, Response::HTTP_FORBIDDEN, 'Forbidden.');

        return $account;
    }

    public function visibleStudents(User $user): Collection
    {
        $account = $this->resolveActiveAccount($user);
        $today = now()->toDateString();

        return PreschoolStudent::query()
            ->with(['classes'])
            ->whereIn('id', function ($query) use ($account, $today): void {
                $query->select('student_id')
                    ->from('preschool_student_guardians')
                    ->where('guardian_id', $account->guardian_id)
                    ->where('status', PreschoolGuardianStatus::ACTIVE)
                    ->where(function ($builder) use ($today): void {
                        $builder->whereNull('starts_at')
                            ->orWhereDate('starts_at', '<=', $today);
                    })
                    ->where(function ($builder) use ($today): void {
                        $builder->whereNull('ends_at')
                            ->orWhereDate('ends_at', '>=', $today);
                    });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function visibleStudentIds(User $user): array
    {
        return $this->visibleStudents($user)->pluck('id')->map(static fn ($id) => (int) $id)->all();
    }

    public function ensureCanAccessStudent(User $user, PreschoolStudent $student): void
    {
        $visibleIds = $this->visibleStudentIds($user);
        abort_unless(in_array((int) $student->id, $visibleIds, true), Response::HTTP_FORBIDDEN, 'Forbidden.');
    }

    public function activeRelationshipsForStudent(User $user, PreschoolStudent $student): Collection
    {
        $this->ensureCanAccessStudent($user, $student);

        return PreschoolStudentGuardian::query()
            ->with(['guardian'])
            ->where('student_id', $student->id)
            ->where('status', PreschoolGuardianStatus::ACTIVE)
            ->orderByDesc('is_primary')
            ->orderByRaw('COALESCE(emergency_priority, 999999) ASC')
            ->orderBy('created_at')
            ->get();
    }
}
