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
            ->where('status', 'active')
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->first();
    }

    public function activeMembershipsForPlayer(SportPlayer $player): Collection
    {
        return SportPlayerTeamMembership::query()
            ->with(['team', 'player'])
            ->where('player_id', $player->id)
            ->where('status', 'active')
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->get();
    }

    public function playerCanJoinTeam(SportPlayer $player, SportTeam $team): bool
    {
        $activeMembership = $this->currentActiveMembership($player);

        return ! $activeMembership || (int) $activeMembership->team_id === (int) $team->id;
    }

    public function createPendingMembership(SportPlayer $player, SportTeam $team, ?User $createdBy = null): SportPlayerTeamMembership
    {
        $membership = SportPlayerTeamMembership::query()->firstOrNew([
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);

        $membership->forceFill([
            'status' => 'pending',
            'joined_at' => $membership->joined_at ?? Carbon::now(),
            'left_at' => null,
            'created_by_user_id' => $createdBy?->id ?? $membership->created_by_user_id,
        ])->save();

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
                    'status' => 'inactive',
                    'left_at' => $now,
                ])->save();
            }

            $membership = SportPlayerTeamMembership::query()->firstOrNew([
                'player_id' => $player->id,
                'team_id' => $team->id,
            ]);

            $membership->forceFill([
                'status' => 'active',
                'joined_at' => $membership->joined_at ?? $now,
                'left_at' => null,
                'created_by_user_id' => $membership->created_by_user_id ?? $changedBy?->id,
            ])->save();

            $player->forceFill([
                'team_id' => $team->id,
            ])->save();

            return $membership->refresh()->loadMissing(['team', 'player']);
        });
    }

    public function deactivateMembership(SportPlayerTeamMembership $membership, ?User $changedBy = null): SportPlayerTeamMembership
    {
        $membership->forceFill([
            'status' => 'inactive',
            'left_at' => Carbon::now(),
        ])->save();

        return $membership->refresh()->loadMissing(['team', 'player']);
    }

    public function deactivateAllMembershipsForPlayer(SportPlayer $player): void
    {
        SportPlayerTeamMembership::query()
            ->where('player_id', $player->id)
            ->whereIn('status', ['active', 'pending'])
            ->update([
                'status' => 'inactive',
                'left_at' => Carbon::now(),
            ]);
    }
}
