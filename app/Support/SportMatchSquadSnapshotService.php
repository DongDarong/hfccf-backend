<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Models\SportPlayerTeamMembership;
use App\Models\SportTeam;
use App\Models\User;
use Illuminate\Support\Carbon;

class SportMatchSquadSnapshotService
{
    public function buildPlayerSnapshot(
        SportMatch $match,
        SportTeam $team,
        SportPlayer $player,
        string $role,
        string $eligibilityStatus,
        bool $isEligible,
        ?string $reason = null,
        ?User $selectedBy = null,
    ): array {
        // These snapshots intentionally freeze the selection-time state so later roster changes do not rewrite match history.
        return [
            'match_id' => $match->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'player_name_snapshot' => trim($player->first_name.' '.$player->last_name),
            'jersey_number_snapshot' => $player->jersey_number,
            'position_snapshot' => $player->primary_position ?? $player->position,
            'role' => $role,
            'eligibility_status' => $eligibilityStatus,
            'is_eligible' => $isEligible,
            'reason' => $reason,
            'selected_at' => Carbon::now(),
        ];
    }

    public function buildMembershipSnapshot(SportPlayerTeamMembership $membership): array
    {
        return [
            'teamId' => $membership->team_id,
            'playerId' => $membership->player_id,
            'status' => $membership->status,
            'joinedAt' => $membership->joined_at?->toISOString(),
            'leftAt' => $membership->left_at?->toISOString(),
        ];
    }
}
