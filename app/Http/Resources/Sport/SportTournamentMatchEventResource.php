<?php

namespace App\Http\Resources\Sport;

use App\Models\SportMatchEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportMatchEvent */
class SportTournamentMatchEventResource extends JsonResource
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
            'createdByUserId' => $this->created_by_user_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
