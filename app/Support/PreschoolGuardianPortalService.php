<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianPortalAccount;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianPortalService
{
    /**
     * Keep portal account listing isolated from guardian/contact data. This is
     * a legacy compatibility surface for admin oversight, not a supported
     * parent-facing login workflow.
     */
    public function listAccounts(User $actor, array $filters = []): LengthAwarePaginator
    {
        $this->ensureAdminAccess($actor);

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $query = PreschoolGuardianPortalAccount::query()->with([
            'guardian',
            'user',
            'invitedBy',
        ]);

        if ($search !== '') {
            $query->where(static function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where('email', 'like', $like)
                    ->orWhereHas('guardian', static function (Builder $guardianQuery) use ($like): void {
                        $guardianQuery->where('full_name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    })
                    ->orWhereHas('user', static function (Builder $userQuery) use ($like): void {
                        $userQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }

        if ($status !== '' && in_array($status, PreschoolGuardianPortalStatus::values(), true)) {
            $query->where('status', $status);
        }

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function ensureAdminAccess(?User $user): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        abort_unless(in_array($user->role_code, ['superadmin', 'adminpreschool'], true), Response::HTTP_FORBIDDEN, 'Forbidden.');
    }

    public function normalizeEmail(PreschoolGuardian $guardian, ?string $email = null): string
    {
        $candidate = strtolower(trim((string) ($email ?? $guardian->email ?? '')));

        abort_if($candidate === '', Response::HTTP_UNPROCESSABLE_ENTITY, 'Guardian email is required for portal access.');

        return $candidate;
    }

    public function generateActivationToken(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'token' => $token,
            'tokenHash' => hash('sha256', $token),
            'expiresAt' => now()->addDays(7)->toISOString(),
        ];
    }
}
