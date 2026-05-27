<?php

namespace App\Http\Resources\Sport;

use App\Models\SportMatchEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportMatchEvent */
class SportMatchEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournamentId' => $this->tournament_id,
            'matchId' => $this->match_id,
            'teamId' => $this->team_id,
            'squadId' => $this->squad_id,
            'squadPlayerId' => $this->squad_player_id,
            'relatedSquadPlayerId' => $this->related_squad_player_id,
            'playerId' => $this->player_id,
            'assistPlayerId' => $this->assist_player_id,
            'playerInId' => $this->player_in_id,
            'playerOutId' => $this->player_out_id,
            'eventType' => $this->event_type,
            'minute' => $this->minute,
            'stoppageMinute' => $this->stoppage_minute,
            'extraTimeMinute' => $this->extra_time_minute,
            'period' => $this->period,
            'side' => $this->side,
            'description' => $this->description,
            'playerNameSnapshot' => $this->player_name_snapshot,
            'jerseyNumberSnapshot' => $this->jersey_number_snapshot,
            'positionSnapshot' => $this->position_snapshot,
            'metadata' => $this->metadata,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
            ]),
            'squad' => $this->whenLoaded('squad', fn (): array => [
                'id' => $this->squad?->id,
                'matchId' => $this->squad?->match_id,
                'teamId' => $this->squad?->team_id,
                'status' => $this->squad?->status,
            ]),
            'squadPlayer' => $this->whenLoaded('squadPlayer', fn (): array => [
                'id' => $this->squadPlayer?->id,
                'playerId' => $this->squadPlayer?->player_id,
                'playerNameSnapshot' => $this->squadPlayer?->player_name_snapshot,
                'jerseyNumberSnapshot' => $this->squadPlayer?->jersey_number_snapshot,
                'positionSnapshot' => $this->squadPlayer?->position_snapshot,
                'role' => $this->squadPlayer?->role,
                'eligibilityStatus' => $this->squadPlayer?->eligibility_status,
                'isEligible' => (bool) $this->squadPlayer?->is_eligible,
            ]),
            'relatedSquadPlayer' => $this->whenLoaded('relatedSquadPlayer', fn (): array => [
                'id' => $this->relatedSquadPlayer?->id,
                'playerId' => $this->relatedSquadPlayer?->player_id,
                'playerNameSnapshot' => $this->relatedSquadPlayer?->player_name_snapshot,
                'jerseyNumberSnapshot' => $this->relatedSquadPlayer?->jersey_number_snapshot,
                'positionSnapshot' => $this->relatedSquadPlayer?->position_snapshot,
                'role' => $this->relatedSquadPlayer?->role,
                'eligibilityStatus' => $this->relatedSquadPlayer?->eligibility_status,
                'isEligible' => (bool) $this->relatedSquadPlayer?->is_eligible,
            ]),
            'player' => $this->whenLoaded('player', fn (): array => [
                'id' => $this->player?->id,
                'name' => trim(($this->player?->first_name ?? '').' '.($this->player?->last_name ?? '')),
                'jerseyNumber' => $this->player?->jersey_number,
                'position' => $this->player?->position,
            ]),
            'assistPlayer' => $this->whenLoaded('assistPlayer', fn (): array => [
                'id' => $this->assistPlayer?->id,
                'name' => trim(($this->assistPlayer?->first_name ?? '').' '.($this->assistPlayer?->last_name ?? '')),
                'jerseyNumber' => $this->assistPlayer?->jersey_number,
                'position' => $this->assistPlayer?->position,
            ]),
            'playerIn' => $this->whenLoaded('playerIn', fn (): array => [
                'id' => $this->playerIn?->id,
                'name' => trim(($this->playerIn?->first_name ?? '').' '.($this->playerIn?->last_name ?? '')),
                'jerseyNumber' => $this->playerIn?->jersey_number,
                'position' => $this->playerIn?->position,
            ]),
            'playerOut' => $this->whenLoaded('playerOut', fn (): array => [
                'id' => $this->playerOut?->id,
                'name' => trim(($this->playerOut?->first_name ?? '').' '.($this->playerOut?->last_name ?? '')),
                'jerseyNumber' => $this->playerOut?->jersey_number,
                'position' => $this->playerOut?->position,
            ]),
            'createdByUserId' => $this->created_by_user_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
