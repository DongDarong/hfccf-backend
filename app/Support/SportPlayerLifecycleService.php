<?php

namespace App\Support;

use App\Models\SportPlayer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SportPlayerLifecycleService
{
    public function __construct(
        private readonly SportPlayerMembershipService $membershipService,
        private readonly SportPlayerStatusTransitionService $transitionService,
    ) {}

    public function history(SportPlayer $player): SportPlayer
    {
        return $player->loadMissing(['team', 'createdBy', 'approvedBy', 'activeMembership.team', 'memberships.team', 'memberships.createdBy', 'memberships.updatedBy']);
    }

    public function setRosterStatus(SportPlayer $player, string $status, ?User $changedBy = null, array $context = []): SportPlayer
    {
        return DB::transaction(function () use ($player, $status, $changedBy, $context): SportPlayer {
            $player = $this->transitionService->transition($player, $status, $changedBy, $context);

            if (in_array($status, [SportPlayerRosterStatus::RELEASED, SportPlayerRosterStatus::GRADUATED, SportPlayerRosterStatus::ARCHIVED], true)) {
                $this->membershipService->deactivateAllMembershipsForPlayer($player, $changedBy);
                $player->forceFill([
                    'team_id' => null,
                ])->save();
            }

            return $player->refresh()->loadMissing(['team', 'createdBy', 'approvedBy', 'activeMembership.team', 'memberships.team']);
        });
    }

    public function markInjured(SportPlayer $player, ?string $notes, ?User $changedBy = null): SportPlayer
    {
        return $this->setRosterStatus($player, SportPlayerRosterStatus::INJURED, $changedBy, [
            'injury_status' => 'injured',
            'notes' => $notes,
        ]);
    }

    public function markSuspended(SportPlayer $player, ?string $notes, ?Carbon $suspensionUntil = null, ?User $changedBy = null): SportPlayer
    {
        return DB::transaction(function () use ($player, $notes, $suspensionUntil, $changedBy): SportPlayer {
            $player = $this->setRosterStatus($player, SportPlayerRosterStatus::SUSPENDED, $changedBy, [
                'disciplinary_status' => 'suspended',
                'notes' => $notes,
            ]);

            $player->memberships()
                ->where('status', SportMembershipStatus::ACTIVE)
                ->update([
                    'status' => SportMembershipStatus::SUSPENDED,
                    'suspension_until' => $suspensionUntil,
                    'notes' => $notes,
                    'updated_by_user_id' => $changedBy?->id,
                    'left_at' => null,
                ]);

            return $player->refresh()->loadMissing(['team', 'memberships.team', 'activeMembership.team']);
        });
    }

    public function release(SportPlayer $player, ?string $notes = null, ?User $changedBy = null): SportPlayer
    {
        return $this->setRosterStatus($player, SportPlayerRosterStatus::RELEASED, $changedBy, [
            'notes' => $notes,
        ]);
    }

    public function archive(SportPlayer $player, ?string $notes = null, ?User $changedBy = null): SportPlayer
    {
        return $this->setRosterStatus($player, SportPlayerRosterStatus::ARCHIVED, $changedBy, [
            'notes' => $notes,
        ]);
    }

    public function restore(SportPlayer $player, ?User $changedBy = null): SportPlayer
    {
        return $this->setRosterStatus($player, SportPlayerRosterStatus::INACTIVE, $changedBy, [
            'notes' => 'Restored from archive.',
        ]);
    }
}
