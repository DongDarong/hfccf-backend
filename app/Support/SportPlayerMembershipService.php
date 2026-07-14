<?php

namespace App\Support;

use App\Models\SportPlayer;
use App\Models\SportPlayerTeamMembership;
use App\Models\SportTeam;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SportPlayerMembershipService
{
    public function currentActiveMembership(SportPlayer $player): ?SportPlayerTeamMembership
    {
        return SportPlayerTeamMembership::query()
            ->with(['team', 'player'])
            ->where('player_id', $player->id)
            ->where('status', SportMembershipStatus::ACTIVE)
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->first();
    }

    public function activeMembershipsForPlayer(SportPlayer $player): Collection
    {
        return SportPlayerTeamMembership::query()
            ->with(['team', 'player'])
            ->where('player_id', $player->id)
            ->where('status', SportMembershipStatus::ACTIVE)
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->get();
    }

    public function playerCanJoinTeam(SportPlayer $player, ?SportTeam $team): bool
    {
        $activeMembership = $this->currentActiveMembership($player);

        if (! $team) {
            return ! $activeMembership;
        }

        return ! $activeMembership || (int) $activeMembership->team_id === (int) $team->id;
    }

    public function createPendingMembership(SportPlayer $player, SportTeam $team, ?User $createdBy = null): SportPlayerTeamMembership
    {
        $membership = SportPlayerTeamMembership::query()->firstOrNew([
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);

        $membership->forceFill([
            'status' => SportMembershipStatus::INACTIVE,
            'joined_at' => $membership->joined_at ?? Carbon::now(),
            'left_at' => null,
            'created_by_user_id' => $createdBy?->id ?? $membership->created_by_user_id,
            'updated_by_user_id' => $createdBy?->id ?? $membership->updated_by_user_id,
        ])->save();

        $this->syncTeamPlayerCount($team);

        return $membership->refresh()->loadMissing(['team', 'player']);
    }

    public function activateMembership(SportPlayer $player, SportTeam $team, ?User $changedBy = null, bool $forceTransfer = false): SportPlayerTeamMembership
    {
        return DB::transaction(function () use ($player, $team, $changedBy, $forceTransfer): SportPlayerTeamMembership {
            $now = Carbon::now();

            $existingActive = $this->currentActiveMembership($player);
            if ($existingActive && (int) $existingActive->team_id !== (int) $team->id) {
                if (! $forceTransfer) {
                    throw new \RuntimeException('Player already belongs to another active team.');
                }

                $existingActive->forceFill([
                    'status' => SportMembershipStatus::INACTIVE,
                    'left_at' => $now,
                    'updated_by_user_id' => $changedBy?->id,
                ])->save();
            }

            $membership = SportPlayerTeamMembership::query()->firstOrNew([
                'player_id' => $player->id,
                'team_id' => $team->id,
            ]);

            $membership->forceFill([
                'status' => SportMembershipStatus::ACTIVE,
                'joined_at' => $membership->joined_at ?? $now,
                'left_at' => null,
                'created_by_user_id' => $membership->created_by_user_id ?? $changedBy?->id,
                'updated_by_user_id' => $changedBy?->id,
            ])->save();

            $player->forceFill([
                'team_id' => $team->id,
            ])->save();

            if ($existingActive && (int) $existingActive->team_id !== (int) $team->id && $existingActive->team) {
                $this->syncTeamPlayerCount($existingActive->team);
            }

            $this->syncTeamPlayerCount($team);

            return $membership->refresh()->loadMissing(['team', 'player']);
        });
    }

    public function deactivateMembership(SportPlayerTeamMembership $membership, ?User $changedBy = null): SportPlayerTeamMembership
    {
        $membership->forceFill([
            'status' => SportMembershipStatus::INACTIVE,
            'left_at' => Carbon::now(),
            'updated_by_user_id' => $changedBy?->id,
        ])->save();

        if ($membership->team) {
            $this->syncTeamPlayerCount($membership->team);
        }

        return $membership->refresh()->loadMissing(['team', 'player']);
    }

    public function deactivateAllMembershipsForPlayer(SportPlayer $player, ?User $changedBy = null): void
    {
        $teamIds = SportPlayerTeamMembership::query()
            ->where('player_id', $player->id)
            ->distinct()
            ->pluck('team_id')
            ->filter()
            ->values()
            ->all();

        SportPlayerTeamMembership::query()
            ->where('player_id', $player->id)
            ->whereIn('status', [SportMembershipStatus::ACTIVE, SportMembershipStatus::INACTIVE, SportMembershipStatus::SUSPENDED])
            ->update([
                'status' => SportMembershipStatus::INACTIVE,
                'left_at' => Carbon::now(),
                'updated_by_user_id' => $changedBy?->id,
            ]);

        $teams = SportTeam::query()
            ->whereIn('id', $teamIds)
            ->get();

        foreach ($teams as $team) {
            $this->syncTeamPlayerCount($team);
        }
    }

    public function playerHasMembershipHistory(SportPlayer $player): Collection
    {
        return SportPlayerTeamMembership::query()
            ->with(['team', 'player', 'createdBy', 'updatedBy'])
            ->where('player_id', $player->id)
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->get();
    }

    public function syncTeamPlayerCount(SportTeam $team): void
    {
        $team->forceFill([
            'players_count' => $team->players()->count(),
        ])->saveQuietly();
    }
}
