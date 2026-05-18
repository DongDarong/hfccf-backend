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
            'groupId' => $this->group_id,
            'knockoutRoundId' => $this->knockout_round_id,
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
            'matchType' => $this->match_type,
            'tournamentName' => $this->tournament_name,
            'roundName' => $this->round_name,
            'matchday' => $this->matchday,
            'venue' => $this->venue,
            'scheduledAt' => $this->scheduled_at?->toISOString(),
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'status' => $this->status,
            'approvalStatus' => $this->approval_status,
            'approvedByUserId' => $this->approved_by_user_id,
            'approvedAt' => $this->approved_at?->toISOString(),
            'rejectionReason' => $this->rejection_reason,
            'requestedByRole' => $this->requested_by_role,
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator?->id,
                'firstName' => $this->creator?->first_name,
                'lastName' => $this->creator?->last_name,
                'username' => $this->creator?->username,
                'email' => $this->creator?->email,
            ]),
            'approvedBy' => $this->whenLoaded('approvedBy', fn (): array => [
                'id' => $this->approvedBy?->id,
                'firstName' => $this->approvedBy?->first_name,
                'lastName' => $this->approvedBy?->last_name,
                'username' => $this->approvedBy?->username,
                'email' => $this->approvedBy?->email,
            ]),
            'currentPeriod' => $this->current_period,
            'homeScore' => (int) $this->home_score,
            'awayScore' => (int) $this->away_score,
            'extraTimeHomeScore' => (int) $this->extra_time_home_score,
            'extraTimeAwayScore' => (int) $this->extra_time_away_score,
            'penaltyHomeScore' => (int) $this->penalty_home_score,
            'penaltyAwayScore' => (int) $this->penalty_away_score,
            'winnerTeamId' => $this->winner_team_id,
            'score' => sprintf('%d - %d', (int) $this->home_score, (int) $this->away_score),
            'fullScore' => sprintf(
                '%d - %d%s',
                (int) $this->home_score,
                (int) $this->away_score,
                $this->penalty_home_score || $this->penalty_away_score
                    ? sprintf(' (%d-%d pens)', (int) $this->penalty_home_score, (int) $this->penalty_away_score)
                    : '',
            ),
            'metadata' => $this->metadata,
            'notes' => $this->notes,
            'createdByUserId' => $this->created_by_user_id,
            'eventsCount' => $this->events_count ?? $this->whenCounted('events'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
