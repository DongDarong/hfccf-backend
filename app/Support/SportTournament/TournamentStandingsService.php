<?php

namespace App\Support\SportTournament;

use App\Models\SportMatch;
use App\Models\SportTournament;
use App\Models\SportTournamentGroup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TournamentStandingsService
{
    /**
     * Build standings directly from match results so recalculation always uses
     * the current source of truth instead of a cached standings table.
     *
     * @return array{
     *   tournament: SportTournament,
     *   groups: array<int, array{
     *     group: SportTournamentGroup,
     *     standings: array<int, array<string, mixed>>
     *   }>,
     *   overall: array<int, array<string, mixed>>
     * }
     */
    public function calculate(int|SportTournament $tournament): array
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament->loadMissing(['groups.groupTeams.team', 'matches'])
            : SportTournament::query()->with(['groups.groupTeams.team', 'matches'])->findOrFail($tournament);

        $groups = $resolvedTournament->groups
            ->sortBy('position')
            ->values();

        $groupSummaries = [];
        $overallRows = [];

        foreach ($groups as $group) {
            $groupRows = $this->calculateGroupStandings($resolvedTournament, $group);
            $groupSummaries[] = [
                'group' => $group,
                'standings' => $groupRows,
            ];

            foreach ($groupRows as $row) {
                $overallRows[] = $row;
            }
        }

        $overall = $this->rankRows($overallRows, collect($resolvedTournament->matches), []);

        return [
            'tournament' => $resolvedTournament,
            'groups' => $groupSummaries,
            'overall' => $overall,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function calculateGroupStandings(SportTournament $tournament, SportTournamentGroup $group): array
    {
        $group->loadMissing(['groupTeams.team']);

        $teamRows = [];
        $teamNames = [];

        foreach ($group->groupTeams as $groupTeam) {
            $teamId = (int) $groupTeam->team_id;
            $teamNames[$teamId] = (string) ($groupTeam->team?->name ?? '');
            $teamRows[$teamId] = [
                'tournament_id' => (int) $tournament->id,
                'group_id' => (int) $group->id,
                'group_code' => (string) $group->code,
                'group_name' => (string) $group->name,
                'team_id' => $teamId,
                'team_name' => $teamNames[$teamId],
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_difference' => 0,
                'points' => 0,
                'qualification_slots' => max(0, (int) $group->qualification_slots),
                'qualified' => false,
                'rank_position' => 0,
            ];
        }

        $matches = $this->groupMatches($tournament, $group->id);

        foreach ($matches as $match) {
            if (! $this->shouldCountMatch($match)) {
                continue;
            }

            $homeTeamId = (int) $match->home_team_id;
            $awayTeamId = (int) $match->away_team_id;

            if (! isset($teamRows[$homeTeamId], $teamRows[$awayTeamId])) {
                continue;
            }

            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            $teamRows[$homeTeamId]['played']++;
            $teamRows[$awayTeamId]['played']++;
            $teamRows[$homeTeamId]['goals_for'] += $homeScore;
            $teamRows[$homeTeamId]['goals_against'] += $awayScore;
            $teamRows[$awayTeamId]['goals_for'] += $awayScore;
            $teamRows[$awayTeamId]['goals_against'] += $homeScore;

            if ($homeScore > $awayScore) {
                $teamRows[$homeTeamId]['wins']++;
                $teamRows[$homeTeamId]['points'] += 3;
                $teamRows[$awayTeamId]['losses']++;

                continue;
            }

            if ($awayScore > $homeScore) {
                $teamRows[$awayTeamId]['wins']++;
                $teamRows[$awayTeamId]['points'] += 3;
                $teamRows[$homeTeamId]['losses']++;

                continue;
            }

            $teamRows[$homeTeamId]['draws']++;
            $teamRows[$awayTeamId]['draws']++;
            $teamRows[$homeTeamId]['points']++;
            $teamRows[$awayTeamId]['points']++;
        }

        foreach ($teamRows as &$row) {
            $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
        }
        unset($row);

        $ranked = $this->rankRows(array_values($teamRows), $matches, $teamNames);

        $qualificationSlots = max(0, (int) $group->qualification_slots);
        foreach ($ranked as $index => &$row) {
            $position = $index + 1;
            $row['rank_position'] = $position;
            $row['qualified'] = $qualificationSlots > 0 && $position <= $qualificationSlots;
        }
        unset($row);

        return $ranked;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, SportMatch>  $matches
     * @param  array<int, string>  $teamNames
     * @return array<int, array<string, mixed>>
     */
    private function rankRows(array $rows, Collection $matches, array $teamNames): array
    {
        $rows = collect($rows)
            ->sort(function (array $left, array $right) use ($matches, $teamNames): int {
                foreach (['points', 'goal_difference', 'goals_for'] as $field) {
                    if ($left[$field] !== $right[$field]) {
                        return $right[$field] <=> $left[$field];
                    }
                }

                $headToHead = $this->compareHeadToHead($left, $right, $matches);
                if ($headToHead !== 0) {
                    return $headToHead;
                }

                $leftName = $teamNames[$left['team_id']] ?? ($left['team_name'] ?? '');
                $rightName = $teamNames[$right['team_id']] ?? ($right['team_name'] ?? '');

                if ($leftName !== $rightName) {
                    return strcasecmp((string) $leftName, (string) $rightName);
                }

                return (int) $left['team_id'] <=> (int) $right['team_id'];
            })
            ->values()
            ->all();

        return $rows;
    }

    private function compareHeadToHead(array $left, array $right, Collection $matches): int
    {
        $leftId = (int) $left['team_id'];
        $rightId = (int) $right['team_id'];

        $headToHeadMatches = $matches->filter(function (SportMatch $match) use ($leftId, $rightId): bool {
            if ((int) $match->home_team_id === $leftId && (int) $match->away_team_id === $rightId) {
                return true;
            }

            return (int) $match->home_team_id === $rightId && (int) $match->away_team_id === $leftId;
        })->values();

        if ($headToHeadMatches->isEmpty()) {
            return 0;
        }

        $leftPoints = 0;
        $rightPoints = 0;
        $leftGoalsFor = 0;
        $leftGoalsAgainst = 0;
        $rightGoalsFor = 0;
        $rightGoalsAgainst = 0;

        foreach ($headToHeadMatches as $match) {
            if (! $this->shouldCountMatch($match)) {
                continue;
            }

            $homeTeamId = (int) $match->home_team_id;
            $awayTeamId = (int) $match->away_team_id;
            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            if ($homeTeamId === $leftId && $awayTeamId === $rightId) {
                $leftGoalsFor += $homeScore;
                $leftGoalsAgainst += $awayScore;
                $rightGoalsFor += $awayScore;
                $rightGoalsAgainst += $homeScore;

                if ($homeScore > $awayScore) {
                    $leftPoints += 3;
                } elseif ($awayScore > $homeScore) {
                    $rightPoints += 3;
                } else {
                    $leftPoints++;
                    $rightPoints++;
                }

                continue;
            }

            if ($homeTeamId === $rightId && $awayTeamId === $leftId) {
                $rightGoalsFor += $homeScore;
                $rightGoalsAgainst += $awayScore;
                $leftGoalsFor += $awayScore;
                $leftGoalsAgainst += $homeScore;

                if ($homeScore > $awayScore) {
                    $rightPoints += 3;
                } elseif ($awayScore > $homeScore) {
                    $leftPoints += 3;
                } else {
                    $leftPoints++;
                    $rightPoints++;
                }
            }
        }

        if ($leftPoints !== $rightPoints) {
            return $rightPoints <=> $leftPoints;
        }

        $leftGoalDifference = $leftGoalsFor - $leftGoalsAgainst;
        $rightGoalDifference = $rightGoalsFor - $rightGoalsAgainst;
        if ($leftGoalDifference !== $rightGoalDifference) {
            return $rightGoalDifference <=> $leftGoalDifference;
        }

        if ($leftGoalsFor !== $rightGoalsFor) {
            return $rightGoalsFor <=> $leftGoalsFor;
        }

        return 0;
    }

    /**
     * @return EloquentCollection<int, SportMatch>
     */
    private function groupMatches(SportTournament $tournament, int $groupId): EloquentCollection
    {
        return $tournament->matches()
            ->where('group_id', $groupId)
            ->orderBy('matchday')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }

    private function shouldCountMatch(SportMatch $match): bool
    {
        return in_array($match->status, ['completed'], true);
    }
}
