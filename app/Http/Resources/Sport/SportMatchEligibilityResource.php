<?php

namespace App\Http\Resources\Sport;

use App\Models\SportPlayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class SportMatchEligibilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SportPlayer|null $player */
        $player = $this->resource['player'] ?? null;
        $team = $this->resource['team'] ?? null;
        $activeMembership = $this->resource['activeMembership'] ?? null;

        return [
            'player' => $player ? [
                'id' => $player->id,
                'playerCode' => $player->player_code,
                'firstName' => $player->first_name,
                'lastName' => $player->last_name,
                'name' => trim($player->first_name.' '.$player->last_name),
                'approvalStatus' => $player->approval_status,
                'rosterStatus' => $player->roster_status ?? $player->status,
            ] : null,
            'team' => $team ? [
                'id' => $team->id,
                'teamCode' => $team->team_code,
                'name' => $team->name,
                'shortName' => $team->short_name,
            ] : null,
            'activeMembership' => $activeMembership ? [
                'id' => $activeMembership->id,
                'teamId' => $activeMembership->team_id,
                'status' => $activeMembership->status,
                'joinedAt' => $activeMembership->joined_at?->toISOString(),
                'leftAt' => $activeMembership->left_at?->toISOString(),
            ] : null,
            'eligibilityStatus' => $this->resource['eligibilityStatus'] ?? null,
            'isEligible' => (bool) ($this->resource['isEligible'] ?? false),
            'reason' => $this->resource['reason'] ?? null,
        ];
    }
}
