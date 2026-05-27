<?php

namespace App\Support;

use App\Models\SportPlayer;
use App\Models\User;
use App\Services\SportActivityRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SportPlayerLifecycleService
{
    public function __construct(
        private readonly SportPlayerMembershipService $membershipService,
        private readonly SportPlayerStatusTransitionService $transitionService,
        private readonly SportActivityRecorder $activityRecorder,
    ) {}

    public function history(SportPlayer $player): SportPlayer
    {
        return $player->loadMissing(['team', 'createdBy', 'approvedBy', 'activeMembership.team', 'memberships.team', 'memberships.createdBy', 'memberships.updatedBy']);
    }

    public function setRosterStatus(SportPlayer $player, string $status, ?User $changedBy = null, array $context = []): SportPlayer
    {
        $before = [
            'roster_status' => $player->roster_status,
            'status' => $player->status,
            'team_id' => $player->team_id,
            'disciplinary_status' => $player->disciplinary_status,
            'injury_status' => $player->injury_status,
            'archived_at' => $player->archived_at?->toISOString(),
        ];

        $player = DB::transaction(function () use ($player, $status, $changedBy, $context): SportPlayer {
            $player = $this->transitionService->transition($player, $status, $changedBy, $context);

            if (in_array($status, [SportPlayerRosterStatus::RELEASED, SportPlayerRosterStatus::GRADUATED, SportPlayerRosterStatus::ARCHIVED], true)) {
                $this->membershipService->deactivateAllMembershipsForPlayer($player, $changedBy);
                $player->forceFill([
                    'team_id' => null,
                ])->save();
            }

            return $player->refresh()->loadMissing(['team', 'createdBy', 'approvedBy', 'activeMembership.team', 'memberships.team']);
        });

        $actor = $changedBy ?? request()->user();
        if ($actor) {
            $this->activityRecorder->playerLifecycleChanged($player, $actor, SportAuditAction::PLAYER_LIFECYCLE_CHANGED, $before, [
                'roster_status' => $player->roster_status,
                'status' => $player->status,
                'team_id' => $player->team_id,
                'disciplinary_status' => $player->disciplinary_status,
                'injury_status' => $player->injury_status,
                'archived_at' => $player->archived_at?->toISOString(),
            ]);
        }

        return $player;
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
