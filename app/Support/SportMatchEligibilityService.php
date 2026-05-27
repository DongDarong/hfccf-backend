<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use Illuminate\Support\Collection;

class SportMatchEligibilityService
{
    public function __construct(
        private readonly SportCoachAssignmentService $assignmentService,
        private readonly SportPlayerMembershipService $membershipService,
    ) {}

    public function playersForMatchTeam(SportMatch $match, SportTeam $team, ?User $actor = null): Collection
    {
        $team->loadMissing(['players.memberships', 'players.activeMembership']);

        return $team->players
            ->map(function (SportPlayer $player) use ($team): array {
                $eligibility = $this->playerEligibility($player, $team);

                return [
                    'player' => $player,
                    'team' => $team,
                    'activeMembership' => $this->membershipService->currentActiveMembership($player),
                    'eligibilityStatus' => $eligibility['eligibilityStatus'],
                    'isEligible' => $eligibility['isEligible'],
                    'reason' => $eligibility['reason'],
                ];
            })
            ->values();
    }

    public function playerEligibility(SportPlayer $player, SportTeam $team): array
    {
        $activeMembership = $this->membershipService->currentActiveMembership($player);
        $teamId = (int) $team->id;
        $memberTeamId = $activeMembership?->team_id ? (int) $activeMembership->team_id : (int) ($player->team_id ?? 0);
        $approvalStatus = (string) ($player->approval_status ?? SportPlayerApprovalStatus::PENDING);
        $rosterStatus = (string) ($player->roster_status ?? $player->status ?? SportPlayerRosterStatus::INACTIVE);

        if ($approvalStatus !== SportPlayerApprovalStatus::APPROVED) {
            return $this->ineligible(SportMatchEligibilityStatus::PENDING, 'Player approval is pending.');
        }

        if ($memberTeamId !== $teamId) {
            return $this->ineligible(SportMatchEligibilityStatus::NOT_MEMBER, 'Player does not belong to the selected team.');
        }

        return match ($rosterStatus) {
            SportPlayerRosterStatus::INJURED => $this->ineligible(SportMatchEligibilityStatus::INJURED, 'Player is injured.'),
            SportPlayerRosterStatus::SUSPENDED => $this->ineligible(SportMatchEligibilityStatus::SUSPENDED, 'Player is suspended.'),
            SportPlayerRosterStatus::INACTIVE => $this->ineligible(SportMatchEligibilityStatus::INACTIVE, 'Player is inactive.'),
            SportPlayerRosterStatus::RELEASED => $this->ineligible(SportMatchEligibilityStatus::RELEASED, 'Player has been released.'),
            SportPlayerRosterStatus::ARCHIVED => $this->ineligible(SportMatchEligibilityStatus::ARCHIVED, 'Player has been archived.'),
            default => [
                'eligibilityStatus' => SportMatchEligibilityStatus::ELIGIBLE,
                'isEligible' => true,
                'reason' => null,
            ],
        };
    }

    public function coachCanManageTeam(User $user, SportTeam $team): bool
    {
        return $this->assignmentService->coachCanManageTeam($user, $team);
    }

    private function ineligible(string $status, string $reason): array
    {
        return [
            'eligibilityStatus' => $status,
            'isEligible' => false,
            'reason' => $reason,
        ];
    }
}
