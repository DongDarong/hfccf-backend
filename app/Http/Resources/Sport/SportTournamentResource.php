<?php

namespace App\Http\Resources\Sport;

use App\Models\SportTournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportTournament */
class SportTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournamentCode' => $this->tournament_code,
            'name' => $this->name,
            'season' => $this->season,
            'tournamentType' => $this->tournament_type,
            'status' => $this->status,
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'description' => $this->description,
            'teamsCount' => $this->teams_count ?? $this->whenCounted('teams'),
            'matchesCount' => $this->matches_count ?? $this->whenCounted('matches'),
            'standingsCount' => $this->standings_count ?? $this->whenCounted('standings'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
