<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use Illuminate\Support\Collection;

class SportMatchScoreService
{
    /**
     * Recalculate and persist the score snapshot for a match.
     */
    public function recalculate(SportMatch $match): SportMatch
    {
        $match->load([
            'homeTeam',
            'awayTeam',
            'events' => fn ($query) => $query->orderBy('minute')->orderBy('stoppage_minute')->orderBy('extra_time_minute')->orderBy('id'),
        ]);

        $scores = $this->calculateFromEvents($match->events, (int) $match->home_team_id, (int) $match->away_team_id);

        $match->forceFill([
            'home_score' => $scores['home'],
            'away_score' => $scores['away'],
        ])->save();

        return $match->refresh()->loadMissing(['homeTeam', 'awayTeam', 'events']);
    }

    /**
     * @param  Collection<int, SportMatchEvent>  $events
     * @return array{home:int, away:int}
     */
    public function calculateFromEvents(Collection $events, int $homeTeamId, int $awayTeamId): array
    {
        $homeScore = 0;
        $awayScore = 0;

        foreach ($events as $event) {
            $eventType = strtolower(trim((string) $event->event_type));
            $teamId = (int) $event->team_id;

            if (! in_array($eventType, ['goal', 'own_goal', 'penalty_goal'], true)) {
                continue;
            }

            $creditedTeamId = match ($eventType) {
                'own_goal' => $teamId === $homeTeamId ? $awayTeamId : $homeTeamId,
                default => $teamId,
            };

            if ($creditedTeamId === $homeTeamId) {
                $homeScore++;
            } elseif ($creditedTeamId === $awayTeamId) {
                $awayScore++;
            }
        }

        return [
            'home' => $homeScore,
            'away' => $awayScore,
        ];
    }
}
