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
            'matchId' => $this->match_id,
            'teamId' => $this->team_id,
            'playerId' => $this->player_id,
            'eventType' => $this->event_type,
            'minute' => $this->minute,
            'extraTimeMinute' => $this->extra_time_minute,
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
            'createdByUserId' => $this->created_by_user_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

