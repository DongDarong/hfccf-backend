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
            'playerId' => $this->player_id,
            'assistPlayerId' => $this->assist_player_id,
            'playerInId' => $this->player_in_id,
            'playerOutId' => $this->player_out_id,
            'eventType' => $this->event_type,
            'minute' => $this->minute,
            'stoppageMinute' => $this->stoppage_minute,
            'extraTimeMinute' => $this->extra_time_minute,
            'side' => $this->side,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
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
