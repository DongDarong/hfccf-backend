<?php

namespace App\Http\Resources\Sport;

use App\Models\SportMatchSquadPlayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportMatchSquadPlayer */
class SportMatchSquadPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'squadId' => $this->squad_id,
            'matchId' => $this->match_id,
            'teamId' => $this->team_id,
            'playerId' => $this->player_id,
            'playerNameSnapshot' => $this->player_name_snapshot,
            'jerseyNumberSnapshot' => $this->jersey_number_snapshot,
            'positionSnapshot' => $this->position_snapshot,
            'role' => $this->role,
            'eligibilityStatus' => $this->eligibility_status,
            'isEligible' => (bool) $this->is_eligible,
            'reason' => $this->reason,
            'selectedAt' => $this->selected_at?->toISOString(),
            'player' => $this->whenLoaded('player', fn (): array => [
                'id' => $this->player?->id,
                'playerCode' => $this->player?->player_code,
                'firstName' => $this->player?->first_name,
                'lastName' => $this->player?->last_name,
                'name' => trim(($this->player?->first_name ?? '').' '.($this->player?->last_name ?? '')),
                'approvalStatus' => $this->player?->approval_status,
                'rosterStatus' => $this->player?->roster_status ?? $this->player?->status,
            ]),
        ];
    }
}
