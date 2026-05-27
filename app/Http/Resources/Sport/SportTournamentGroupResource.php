<?php

namespace App\Http\Resources\Sport;

use App\Models\SportTournamentGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportTournamentGroup */
class SportTournamentGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournamentId' => $this->tournament_id,
            'name' => $this->name,
            'code' => $this->code,
            'position' => (int) $this->position,
            'qualificationSlots' => (int) $this->qualification_slots,
            'status' => $this->status,
            'finalizedAt' => $this->finalized_at?->toISOString(),
            'metadata' => $this->metadata,
            'teamsCount' => $this->groupTeams_count ?? ($this->relationLoaded('groupTeams') ? $this->groupTeams->count() : null),
            'matchesCount' => $this->matches_count ?? ($this->relationLoaded('matches') ? $this->matches->count() : null),
            'teams' => $this->whenLoaded('groupTeams', fn (): array => SportTournamentGroupTeamResource::collection($this->groupTeams)->resolve($request)),
        ];
    }
}
