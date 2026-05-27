<?php

namespace App\Http\Resources\Sport;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportTournamentStandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'groupId' => $this['group_id'] ?? null,
            'groupCode' => $this['group_code'] ?? null,
            'groupName' => $this['group_name'] ?? null,
            'teamId' => $this['team_id'] ?? null,
            'teamName' => $this['team_name'] ?? null,
            'rankPosition' => $this['rank_position'] ?? null,
            'played' => (int) ($this['played'] ?? 0),
            'wins' => (int) ($this['wins'] ?? 0),
            'draws' => (int) ($this['draws'] ?? 0),
            'losses' => (int) ($this['losses'] ?? 0),
            'goalsFor' => (int) ($this['goals_for'] ?? 0),
            'goalsAgainst' => (int) ($this['goals_against'] ?? 0),
            'goalDifference' => (int) ($this['goal_difference'] ?? 0),
            'points' => (int) ($this['points'] ?? 0),
            'qualified' => (bool) ($this['qualified'] ?? false),
            'qualificationSlots' => (int) ($this['qualification_slots'] ?? 0),
        ];
    }
}
