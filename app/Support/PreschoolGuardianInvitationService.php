<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianPortalAccount;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreschoolGuardianInvitationService
{
    /**
     * Invitation state is the only place where guardian data becomes login
     * access, so revocation and activation stay auditable and reversible.
     */
    public function invite(User $actor, PreschoolGuardian $guardian, array $data = []): array
    {
        app(PreschoolGuardianPortalService::class)->ensureAdminAccess($actor);

        $email = app(PreschoolGuardianPortalService::class)->normalizeEmail($guardian, $data['email'] ?? null);
        $tokenPayload = app(PreschoolGuardianPortalService::class)->generateActivationToken();

        $account = DB::transaction(function () use ($actor, $guardian, $email, $tokenPayload): PreschoolGuardianPortalAccount {
            $account = PreschoolGuardianPortalAccount::query()
                ->where('guardian_id', $guardian->id)
                ->first();

            if (! $account) {
                $account = new PreschoolGuardianPortalAccount;
                $account->guardian_id = $guardian->id;
            }

            $account->email = $email;
            $account->status = PreschoolGuardianPortalStatus::INVITED;
            $account->invited_by_user_id = $actor->id;
            $account->invited_at = now();
            $account->activated_at = null;
            $account->revoked_at = null;
            $account->last_login_at = null;
            $account->metadata = [
                'activation_token_hash' => $tokenPayload['tokenHash'],
                'activation_token_expires_at' => $tokenPayload['expiresAt'],
            ];
            $account->save();

            return $account->fresh(['guardian', 'user', 'invitedBy']);
        });

        return [
            'account' => $account,
            'activationToken' => $tokenPayload['token'],
            'activationUrl' => '/guardian-portal/activate?token='.$tokenPayload['token'],
        ];
    }

    public function revoke(User $actor, PreschoolGuardianPortalAccount $account): PreschoolGuardianPortalAccount
    {
        app(PreschoolGuardianPortalService::class)->ensureAdminAccess($actor);

        return DB::transaction(function () use ($account): PreschoolGuardianPortalAccount {
            $account->status = PreschoolGuardianPortalStatus::REVOKED;
            $account->revoked_at = now();
            $account->metadata = Arr::except((array) $account->metadata, ['activation_token_hash', 'activation_token_expires_at']);
            $account->save();

            if ($account->user) {
                $account->user->forceFill([
                    'status' => 'suspended',
                ])->save();

                $account->user->tokens()->delete();
            }

            return $account->fresh(['guardian', 'user', 'invitedBy']);
        });
    }

    public function activate(string $token, array $data): array
    {
        $tokenHash = hash('sha256', $token);
        $account = PreschoolGuardianPortalAccount::query()
            ->with(['guardian', 'user'])
            ->get()
            ->first(static function (PreschoolGuardianPortalAccount $account) use ($tokenHash): bool {
                $metadata = is_array($account->metadata) ? $account->metadata : [];
                $storedHash = (string) ($metadata['activation_token_hash'] ?? '');
                $expiresAt = (string) ($metadata['activation_token_expires_at'] ?? '');

                return $account->status === PreschoolGuardianPortalStatus::INVITED
                    && hash_equals($storedHash, $tokenHash)
                    && $expiresAt !== ''
                    && Carbon::parse($expiresAt)->isFuture();
            });

        if (! $account || ! $account->guardian) {
            throw ValidationException::withMessages([
                'token' => 'The guardian portal invitation token is invalid or expired.',
            ]);
        }

        $guardian = $account->guardian;
        $user = $account->user;
        $email = strtolower(trim((string) $account->email));
        $role = Role::query()->find('guardian');

        if (! $role) {
            throw ValidationException::withMessages([
                'token' => 'The guardian role is not available.',
            ]);
        }

        return DB::transaction(function () use ($account, $guardian, $user, $email, $role, $data): array {
            $nameParts = $this->splitName($guardian->full_name);

            if ($user && $user->role_code !== 'guardian') {
                throw ValidationException::withMessages([
                    'token' => 'The portal invitation is linked to a non-guardian account.',
                ]);
            }

            if (! $user) {
                $user = User::query()->create([
                    'id' => $this->nextUserId(),
                    'first_name' => $nameParts['first_name'],
                    'last_name' => $nameParts['last_name'],
                    'username' => $guardian->full_name,
                    'email' => $email,
                    'phone' => $guardian->phone,
                    'role_code' => $role->code,
                    'department_code' => $role->department_code,
                    'status' => 'active',
                    'password' => $data['password'],
                ]);
            } else {
                $user->forceFill([
                    'first_name' => $nameParts['first_name'],
                    'last_name' => $nameParts['last_name'],
                    'username' => $guardian->full_name,
                    'phone' => $guardian->phone,
                    'role_code' => $role->code,
                    'department_code' => $role->department_code,
                    'status' => 'active',
                    'password' => $data['password'],
                ])->save();
            }

            $user->permissions()->sync([]);

            $account->forceFill([
                'user_id' => $user->id,
                'status' => PreschoolGuardianPortalStatus::ACTIVE,
                'activated_at' => now(),
                'revoked_at' => null,
                'last_login_at' => now(),
                'metadata' => [
                    'activation_token_hash' => null,
                    'activation_token_expires_at' => null,
                ],
            ])->save();

            $user->loadMissing(['department', 'role', 'permissions' => fn ($query) => $query->orderBy('permissions.code')]);
            $tokenResult = $user->createToken('auth-token', ['*'], now()->addHours(12));

            return [
                'account' => $account->fresh(['guardian', 'user', 'invitedBy']),
                'user' => $user,
                'token' => $tokenResult->plainTextToken,
            ];
        });
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstName = array_shift($parts) ?: 'Guardian';
        $lastName = trim(implode(' ', $parts));

        return [
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : 'Portal',
        ];
    }

    private function nextUserId(): string
    {
        $maxNumeric = User::withTrashed()
            ->where('id', 'like', 'usr_%')
            ->pluck('id')
            ->map(static function (string $id): int {
                return (int) preg_replace('/^usr_/', '', $id);
            })
            ->max() ?? 0;

        $next = $maxNumeric + 1;
        $suffix = $next <= 999 ? str_pad((string) $next, 3, '0', STR_PAD_LEFT) : (string) $next;

        return 'usr_'.$suffix;
    }
}
