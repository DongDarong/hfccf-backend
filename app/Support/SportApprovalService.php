<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Models\User;
use App\Services\SportActivityRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SportApprovalService
{
    public function __construct(
        private readonly SportPlayerMembershipService $membershipService,
        private readonly SportActivityRecorder $activityRecorder,
    ) {}

    public function pendingPlayersQuery(): Builder
    {
        return SportPlayer::query()
            ->with(['team', 'createdBy', 'approvedBy'])
            ->where('approval_status', 'pending');
    }

    public function pendingMatchesQuery(): Builder
    {
        return SportMatch::query()
            ->with(['homeTeam', 'awayTeam', 'creator', 'approvedBy'])
            ->where('approval_status', 'pending');
    }

    public function approvePlayer(SportPlayer $player, User $approver): SportPlayer
    {
        $player = DB::transaction(function () use ($player, $approver): SportPlayer {
            $team = $player->team;

            if (! $team) {
                throw new \RuntimeException('Player team is required for approval.');
            }

            if (! $this->membershipService->playerCanJoinTeam($player, $team)) {
                throw new \RuntimeException('Player already belongs to another active team.');
            }

            $player->forceFill([
                'approval_status' => SportPlayerApprovalStatus::APPROVED,
                'roster_status' => SportPlayerRosterStatus::ACTIVE,
                'status' => SportPlayerRosterStatus::ACTIVE,
                'approved_by_user_id' => $approver->id,
                'approved_at' => Carbon::now(),
                'rejection_reason' => null,
                'registration_status' => $player->registration_status === 'pending' ? 'registered' : $player->registration_status,
            ])->save();

            $membership = $this->membershipService->activateMembership($player, $team, $approver, false);
            $this->activityRecorder->rosterMembershipChanged($membership->loadMissing(['team', 'player']), $approver, SportAuditAction::ROSTER_MEMBERSHIP_ADDED);

            return $player->refresh()->loadMissing(['team', 'createdBy', 'approvedBy']);
        });

        $this->activityRecorder->playerDecision($player, $approver, true);

        return $player;
    }

    public function rejectPlayer(SportPlayer $player, User $approver, ?string $reason = null): SportPlayer
    {
        $existingMemberships = $player->memberships()->with(['team', 'player'])->get();

        $player = DB::transaction(function () use ($player, $approver, $reason): SportPlayer {
            $this->membershipService->deactivateAllMembershipsForPlayer($player, $approver);

            $player->forceFill([
                'approval_status' => SportPlayerApprovalStatus::REJECTED,
                'roster_status' => SportPlayerRosterStatus::INACTIVE,
                'status' => SportPlayerRosterStatus::INACTIVE,
                'approved_by_user_id' => $approver->id,
                'approved_at' => Carbon::now(),
                'rejection_reason' => $reason,
            ])->save();

            return $player->refresh()->loadMissing(['team', 'createdBy', 'approvedBy']);
        });

        foreach ($existingMemberships as $membership) {
            $this->activityRecorder->rosterMembershipChanged($membership, $approver, SportAuditAction::ROSTER_MEMBERSHIP_REMOVED);
        }

        $this->activityRecorder->playerDecision($player, $approver, false, $reason);

        return $player;
    }

    public function approveMatch(SportMatch $match, User $approver): SportMatch
    {
        $match->forceFill([
            'approval_status' => 'approved',
            'status' => in_array($match->status, ['draft', 'cancelled'], true) ? 'scheduled' : $match->status,
            'approved_by_user_id' => $approver->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => null,
        ])->save();

        $match = $match->refresh()->loadMissing(['homeTeam', 'awayTeam', 'creator', 'approvedBy']);
        $this->activityRecorder->matchDecision($match, $approver, true);

        return $match;
    }

    public function rejectMatch(SportMatch $match, User $approver, ?string $reason = null): SportMatch
    {
        $match->forceFill([
            'approval_status' => 'rejected',
            'status' => 'cancelled',
            'approved_by_user_id' => $approver->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ])->save();

        $match = $match->refresh()->loadMissing(['homeTeam', 'awayTeam', 'creator', 'approvedBy']);
        $this->activityRecorder->matchDecision($match, $approver, false, $reason);

        return $match;
    }
}
