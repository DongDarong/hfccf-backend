<?php

namespace App\Http\Resources\Sport;

use App\Models\SportTournamentGroupTeam;
use App\Support\SportMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportTournamentGroupTeam */
class SportTournamentGroupTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournamentId' => $this->tournament_id,
            'groupId' => $this->group_id,
            'teamId' => $this->team_id,
            'seed' => $this->seed,
            'pot' => $this->pot,
            'position' => $this->position,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
                'logo' => SportMedia::resolveUrl($this->team?->logo),
                'status' => $this->team?->status,
            ]),
        ];
    }
}
