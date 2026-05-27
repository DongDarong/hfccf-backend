<?php

namespace App\Support;

use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;

class SportPlayerEligibilityService
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportPlayerMembershipService $membershipService,
    ) {}

    public function coachCanManageTeam(User $user, SportTeam $team): bool
    {
        return $this->assignmentService->coachCanManageTeam($user, $team);
    }

    public function playerCanJoinTeam(SportPlayer $player, SportTeam $team): bool
    {
        if (! in_array($player->approval_status, [SportPlayerApprovalStatus::APPROVED], true)) {
            return false;
        }

        if (in_array($player->roster_status ?? $player->status, [SportPlayerRosterStatus::ARCHIVED, SportPlayerRosterStatus::RELEASED], true)) {
            return false;
        }

        $activeMembership = $this->membershipService->currentActiveMembership($player);

        return ! $activeMembership || (int) $activeMembership->team_id === (int) $team->id;
    }

    public function playerHasActiveMembershipElsewhere(SportPlayer $player, SportTeam $team): bool
    {
        $activeMembership = $this->membershipService->currentActiveMembership($player);

        return (bool) $activeMembership && (int) $activeMembership->team_id !== (int) $team->id;
    }

    public function playerIsRosterLocked(SportPlayer $player): bool
    {
        return in_array($player->roster_status ?? $player->status, [
            SportPlayerRosterStatus::ARCHIVED,
            SportPlayerRosterStatus::RELEASED,
        ], true);
    }
}
