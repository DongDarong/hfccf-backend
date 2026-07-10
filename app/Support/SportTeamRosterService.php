<?php

namespace App\Support;

use App\Models\SportPlayer;
use App\Models\SportPlayerTeamMembership;
use App\Models\SportTeam;
use App\Models\User;
use App\Services\SportActivityRecorder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SportTeamRosterService
{
    public function __construct(
        private readonly SportPlayerMembershipService $membershipService,
        private readonly SportPlayerEligibilityService $eligibilityService,
        private readonly SportActivityRecorder $activityRecorder,
    ) {}

    public function rosterForTeam(SportTeam $team): Collection
    {
        return $team->players()
            ->with(['team', 'createdBy', 'approvedBy', 'memberships.team', 'activeMembership'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function eligiblePlayersForTeam(SportTeam $team): Collection
    {
        $players = SportPlayer::query()
            ->with(['team', 'createdBy', 'approvedBy', 'activeMembership', 'memberships.team'])
            ->where('approval_status', SportPlayerApprovalStatus::APPROVED)
            ->where(function ($query): void {
                $query->whereNull('roster_status')
                    ->orWhereNotIn('roster_status', [SportPlayerRosterStatus::ARCHIVED, SportPlayerRosterStatus::RELEASED]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $players->filter(function (SportPlayer $player) use ($team): bool {
            if (! $this->eligibilityService->playerCanJoinTeam($player, $team)) {
                return false;
            }

            $activeMembership = $this->membershipService->currentActiveMembership($player);

            return ! $activeMembership || (int) $activeMembership->team_id !== (int) $team->id;
        })->values();
    }

    public function attachPlayer(SportTeam $team, SportPlayer $player, User $actor, string $membershipStatus = SportMembershipStatus::ACTIVE, ?string $notes = null): SportPlayerTeamMembership
    {
        if (! $this->eligibilityService->playerCanJoinTeam($player, $team)) {
            throw new \RuntimeException('Player cannot join the selected team.');
        }

        $membership = DB::transaction(function () use ($team, $player, $actor, $membershipStatus, $notes): SportPlayerTeamMembership {
            $membership = $this->membershipService->activateMembership($player, $team, $actor, true);

            $membership->forceFill([
                'status' => $membershipStatus,
                'notes' => $notes,
                'updated_by_user_id' => $actor->id,
                'joined_at' => $membership->joined_at ?? Carbon::now(),
            ])->save();

            $player->forceFill([
                'team_id' => $team->id,
                'roster_status' => SportPlayerRosterStatus::ACTIVE,
                'status' => SportPlayerRosterStatus::ACTIVE,
            ])->save();

            return $membership->refresh()->loadMissing(['team', 'player', 'createdBy', 'updatedBy']);
        });

        $this->activityRecorder->rosterMembershipChanged($membership, $actor, SportAuditAction::ROSTER_MEMBERSHIP_ADDED);

        return $membership;
    }

    public function transferPlayer(SportTeam $team, SportPlayer $player, User $actor, ?string $notes = null): SportPlayerTeamMembership
    {
        return DB::transaction(function () use ($team, $player, $actor, $notes): SportPlayerTeamMembership {
            $currentMembership = $this->membershipService->currentActiveMembership($player);
            if ($currentMembership && (int) $currentMembership->team_id !== (int) $team->id) {
                $this->membershipService->deactivateMembership($currentMembership, $actor);
            }

            return $this->attachPlayer($team, $player, $actor, SportMembershipStatus::ACTIVE, $notes);
        });
    }

    public function updateMembership(SportPlayerTeamMembership $membership, array $data, User $actor, string $auditAction = SportAuditAction::ROSTER_MEMBERSHIP_UPDATED): SportPlayerTeamMembership
    {
        $membership = DB::transaction(function () use ($membership, $data, $actor): SportPlayerTeamMembership {
            $player = $membership->player()->first();
            $team = $membership->team()->first();
            $membership->forceFill([
                'status' => strtolower((string) ($data['status'] ?? $membership->status)),
                'suspension_until' => $data['suspension_until'] ?? $membership->suspension_until,
                'injury_notes' => $data['injury_notes'] ?? $membership->injury_notes,
                'notes' => $data['notes'] ?? $membership->notes,
                'updated_by_user_id' => $actor->id,
            ])->save();

            if ($player && $team) {
                if ($membership->status === SportMembershipStatus::ACTIVE && $this->eligibilityService->playerHasActiveMembershipElsewhere($player, $team)) {
                    throw new \RuntimeException('Player already belongs to another active team.');
                }

                $playerStatus = match ($membership->status) {
                    SportMembershipStatus::ACTIVE => SportPlayerRosterStatus::ACTIVE,
                    SportMembershipStatus::SUSPENDED => SportPlayerRosterStatus::SUSPENDED,
                    SportMembershipStatus::RELEASED => SportPlayerRosterStatus::RELEASED,
                    SportMembershipStatus::EXPIRED, SportMembershipStatus::INACTIVE => SportPlayerRosterStatus::INACTIVE,
                    default => $player->roster_status ?? SportPlayerRosterStatus::INACTIVE,
                };

                $player->forceFill([
                    'roster_status' => $playerStatus,
                    'status' => $playerStatus,
                    'team_id' => in_array($membership->status, [SportMembershipStatus::ACTIVE, SportMembershipStatus::LOANED, SportMembershipStatus::SUSPENDED], true)
                        ? $membership->team_id
                        : null,
                ])->save();
            }

            return $membership->refresh()->loadMissing(['team', 'player', 'createdBy', 'updatedBy']);
        });

        $this->activityRecorder->rosterMembershipChanged($membership, $actor, $auditAction);

        return $membership;
    }

    public function deactivateMembership(SportPlayerTeamMembership $membership, User $actor): SportPlayerTeamMembership
    {
        $membership = $this->updateMembership($membership, [
            'status' => SportMembershipStatus::INACTIVE,
            'notes' => $membership->notes,
        ], $actor, SportAuditAction::ROSTER_MEMBERSHIP_REMOVED);

        return $membership;
    }

    public function deactivateAllForPlayer(SportPlayer $player, ?User $actor = null): void
    {
        $this->membershipService->deactivateAllMembershipsForPlayer($player, $actor);
    }
}
