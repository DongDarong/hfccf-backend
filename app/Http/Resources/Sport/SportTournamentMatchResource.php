<?php

namespace App\Http\Resources\Sport;

use App\Models\SportMatch;
use App\Support\SportMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportMatch */
class SportTournamentMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matchCode' => $this->match_code,
            'tournamentId' => $this->tournament_id,
            'groupId' => $this->group_id,
            'knockoutRoundId' => $this->knockout_round_id,
            'homeTeamId' => $this->home_team_id,
            'awayTeamId' => $this->away_team_id,
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
            'group' => $this->whenLoaded('group', fn (): array => [
                'id' => $this->group?->id,
                'name' => $this->group?->name,
                'code' => $this->group?->code,
                'position' => $this->group?->position,
                'status' => $this->group?->status,
            ]),
            'knockoutRound' => $this->whenLoaded('knockoutRound', fn (): array => [
                'id' => $this->knockoutRound?->id,
                'name' => $this->knockoutRound?->name,
                'code' => $this->knockoutRound?->code,
                'position' => $this->knockoutRound?->position,
                'status' => $this->knockoutRound?->status,
            ]),
            'competitionType' => $this->competition_type,
            'tournamentName' => $this->tournament_name,
            'roundName' => $this->round_name,
            'matchday' => $this->matchday,
            'venue' => $this->venue,
            'scheduledAt' => $this->scheduled_at?->toISOString(),
            'status' => $this->status,
            'currentPeriod' => $this->current_period,
            'homeScore' => (int) $this->home_score,
            'awayScore' => (int) $this->away_score,
            'extraTimeHomeScore' => (int) $this->extra_time_home_score,
            'extraTimeAwayScore' => (int) $this->extra_time_away_score,
            'penaltyHomeScore' => (int) $this->penalty_home_score,
            'penaltyAwayScore' => (int) $this->penalty_away_score,
            'winnerTeamId' => $this->winner_team_id,
            'metadata' => $this->metadata,
            'score' => sprintf('%d - %d', (int) $this->home_score, (int) $this->away_score),
            'eventsCount' => $this->events_count ?? $this->whenCounted('events'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
