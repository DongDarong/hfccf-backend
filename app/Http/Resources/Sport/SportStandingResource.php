<?php

namespace App\Http\Resources\Sport;

use App\Models\SportStanding;
use App\Support\SportMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportStanding */
class SportStandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournamentId' => $this->tournament_id,
            'teamId' => $this->team_id,
            'rankPosition' => $this->rank_position,
            'played' => (int) $this->played,
            'wins' => (int) $this->wins,
            'draws' => (int) $this->draws,
            'losses' => (int) $this->losses,
            'goalsFor' => (int) $this->goals_for,
            'goalsAgainst' => (int) $this->goals_against,
            'goalDifference' => (int) $this->goal_difference,
            'points' => (int) $this->points,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
                'logo' => SportMedia::resolveUrl($this->team?->logo),
                'status' => $this->team?->status,
            ]),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
