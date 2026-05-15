<?php

namespace App\Support;

use App\Models\SportMatch;
use App\Models\SportStanding;
use App\Models\SportTeam;
use App\Models\SportTournament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SportStandingsService
{
    public function rebuildTournament(int|SportTournament $tournament): EloquentCollection
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament->loadMissing(['teams'])
            : SportTournament::query()->with(['teams'])->findOrFail($tournament);

        $teamIds = $resolvedTournament->teams->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $teamNames = $resolvedTournament->teams->mapWithKeys(fn (SportTeam $team): array => [
            (int) $team->id => (string) $team->name,
        ])->all();

        if ($teamIds === []) {
            SportStanding::query()->where('tournament_id', $resolvedTournament->id)->delete();

            return new EloquentCollection();
        }

        $completedMatches = SportMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('tournament_id', $resolvedTournament->id)
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();

        $standings = [];

        foreach ($teamIds as $teamId) {
            $standings[$teamId] = [
                'team_id' => $teamId,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_difference' => 0,
                'points' => 0,
            ];
        }

        foreach ($completedMatches as $match) {
            $homeTeamId = (int) $match->home_team_id;
            $awayTeamId = (int) $match->away_team_id;

            if (! isset($standings[$homeTeamId]) || ! isset($standings[$awayTeamId])) {
                continue;
            }

            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            $standings[$homeTeamId]['played']++;
            $standings[$awayTeamId]['played']++;
            $standings[$homeTeamId]['goals_for'] += $homeScore;
            $standings[$homeTeamId]['goals_against'] += $awayScore;
            $standings[$awayTeamId]['goals_for'] += $awayScore;
            $standings[$awayTeamId]['goals_against'] += $homeScore;

            if ($homeScore > $awayScore) {
                $standings[$homeTeamId]['wins']++;
                $standings[$homeTeamId]['points'] += 3;
                $standings[$awayTeamId]['losses']++;
            } elseif ($awayScore > $homeScore) {
                $standings[$awayTeamId]['wins']++;
                $standings[$awayTeamId]['points'] += 3;
                $standings[$homeTeamId]['losses']++;
            } else {
                $standings[$homeTeamId]['draws']++;
                $standings[$awayTeamId]['draws']++;
                $standings[$homeTeamId]['points']++;
                $standings[$awayTeamId]['points']++;
            }
        }

        $sortedStandings = $this->rankStandings(array_values($standings), $teamNames);
        $now = Carbon::now();

        DB::transaction(function () use ($resolvedTournament, $sortedStandings, $now): void {
            SportStanding::query()->where('tournament_id', $resolvedTournament->id)->delete();

            foreach ($sortedStandings as $index => $row) {
                SportStanding::query()->create([
                    'tournament_id' => $resolvedTournament->id,
                    'team_id' => $row['team_id'],
                    'played' => $row['played'],
                    'wins' => $row['wins'],
                    'draws' => $row['draws'],
                    'losses' => $row['losses'],
                    'goals_for' => $row['goals_for'],
                    'goals_against' => $row['goals_against'],
                    'goal_difference' => $row['goals_for'] - $row['goals_against'],
                    'points' => $row['points'],
                    'rank_position' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return SportStanding::query()
            ->with(['team'])
            ->where('tournament_id', $resolvedTournament->id)
            ->orderBy('rank_position')
            ->orderBy('id')
            ->get();
    }

    public function rebuildTournamentById(int $tournamentId): EloquentCollection
    {
        return $this->rebuildTournament($tournamentId);
    }

    /**
     * @param  array<int, array<string, int>>  $standings
     * @param  array<int, string>  $teamNames
     * @return array<int, array<string, int>>
     */
    private function rankStandings(array $standings, array $teamNames): array
    {
        return collect($standings)
            ->map(function (array $row): array {
                $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
                return $row;
            })
            ->sort(function (array $left, array $right) use ($teamNames): int {
                foreach (['points', 'goal_difference', 'goals_for'] as $field) {
                    if ($left[$field] !== $right[$field]) {
                        return $right[$field] <=> $left[$field];
                    }
                }

                $leftTeam = $teamNames[$left['team_id']] ?? '';
                $rightTeam = $teamNames[$right['team_id']] ?? '';

                if ($leftTeam !== $rightTeam) {
                    return $leftTeam <=> $rightTeam;
                }

                return $left['team_id'] <=> $right['team_id'];
            })
            ->values()
            ->all();
    }
}
