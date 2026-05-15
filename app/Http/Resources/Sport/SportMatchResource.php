<?php

namespace App\Http\Resources\Sport;

use App\Models\SportMatch;
use App\Support\SportMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportMatch */
class SportMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matchCode' => $this->match_code,
            'homeTeamId' => $this->home_team_id,
            'awayTeamId' => $this->away_team_id,
            'tournamentId' => $this->tournament_id,
            'homeTeam' => $this->whenLoaded('homeTeam', fn (): array => [
                'id' => $this->homeTeam?->id,
                'teamCode' => $this->homeTeam?->team_code,
                'name' => $this->homeTeam?->name,
                'shortName' => $this->homeTeam?->short_name,
                'logo' => SportMedia::resolveUrl($this->homeTeam?->logo),
            ]),
            'awayTeam' => $this->whenLoaded('awayTeam', fn (): array => [
                'id' => $this->awayTeam?->id,
                'teamCode' => $this->awayTeam?->team_code,
                'name' => $this->awayTeam?->name,
                'shortName' => $this->awayTeam?->short_name,
                'logo' => SportMedia::resolveUrl($this->awayTeam?->logo),
            ]),
            'tournament' => $this->whenLoaded('tournament', fn (): array => [
                'id' => $this->tournament?->id,
                'tournamentCode' => $this->tournament?->tournament_code,
                'name' => $this->tournament?->name,
                'season' => $this->tournament?->season,
                'tournamentType' => $this->tournament?->tournament_type,
                'status' => $this->tournament?->status,
            ]),
            'competitionType' => $this->competition_type,
            'tournamentName' => $this->tournament_name,
            'venue' => $this->venue,
            'scheduledAt' => $this->scheduled_at?->toISOString(),
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'status' => $this->status,
            'currentPeriod' => $this->current_period,
            'homeScore' => (int) $this->home_score,
            'awayScore' => (int) $this->away_score,
            'score' => sprintf('%d - %d', (int) $this->home_score, (int) $this->away_score),
            'notes' => $this->notes,
            'createdByUserId' => $this->created_by_user_id,
            'eventsCount' => $this->events_count ?? $this->whenCounted('events'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
