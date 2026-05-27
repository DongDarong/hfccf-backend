<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianPortalAccount;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class PreschoolGuardianInvitationService
{
    /**
     * Legacy compatibility only: guardian portal invites are preserved as
     * admin audit records, but they no longer bootstrap any login identity or
     * activation token for guardians.
     */
    public function invite(User $actor, PreschoolGuardian $guardian, array $data = []): array
    {
        app(PreschoolGuardianPortalService::class)->ensureAdminAccess($actor);

        $email = app(PreschoolGuardianPortalService::class)->normalizeEmail($guardian, $data['email'] ?? null);

        $account = DB::transaction(function () use ($actor, $guardian, $email): PreschoolGuardianPortalAccount {
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
                'legacy_portal_disabled' => true,
            ];
            $account->save();

            return $account->fresh(['guardian', 'user', 'invitedBy']);
        });

        return [
            'account' => $account,
            'activationDisabled' => true,
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
        abort(410, 'Guardian portal activation is disabled.');
    }
}
