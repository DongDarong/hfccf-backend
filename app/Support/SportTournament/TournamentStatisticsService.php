<?php

namespace App\Support\SportTournament;

use App\Models\SportMatch;
use App\Models\SportMatchEvent;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\SportTournament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TournamentStatisticsService
{
    public function __construct(private readonly TournamentStandingsService $standingsService)
    {
    }

    /**
     * Build tournament statistics from the same match/event source used for
     * standings so analytics cannot drift away from competition results.
     *
     * @return array{
     *   tournament: SportTournament,
     *   summary: array<string, mixed>,
     *   playerStats: array<int, array<string, mixed>>,
     *   teamStats: array<int, array<string, mixed>>
     * }
     */
    public function calculate(int|SportTournament $tournament): array
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament->loadMissing(['groups.groupTeams.team', 'matches.events.player', 'matches.events.assistPlayer', 'matches.events.playerIn', 'matches.events.playerOut', 'matches.homeTeam', 'matches.awayTeam'])
            : SportTournament::query()->with([
                'groups.groupTeams.team',
                'matches.events.player',
                'matches.events.assistPlayer',
                'matches.events.playerIn',
                'matches.events.playerOut',
                'matches.homeTeam',
                'matches.awayTeam',
            ])->findOrFail($tournament);

        $matches = $resolvedTournament->matches;
        $events = $matches->flatMap(fn (SportMatch $match): Collection => $match->events->map(function (SportMatchEvent $event) use ($match): SportMatchEvent {
            $event->setRelation('match', $match);
            return $event;
        }));

        $playerStats = $this->calculatePlayerStats($matches, $events);
        $teamStats = $this->calculateTeamStats($matches, $events);
        $summary = $this->calculateTournamentSummary($matches, $events, $playerStats, $teamStats);

        return [
            'tournament' => $resolvedTournament,
            'summary' => $summary,
            'playerStats' => $playerStats,
            'teamStats' => $teamStats,
        ];
    }

    /**
     * @param  Collection<int, SportMatch>  $matches
     * @param  Collection<int, SportMatchEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function calculatePlayerStats(Collection $matches, Collection $events): array
    {
        $rows = [];

        foreach ($events as $event) {
            $type = $this->normalizeEventType($event->event_type);
            $player = $event->player;
            $assistPlayer = $event->assistPlayer;
            $playerId = (int) ($player?->id ?? 0);

            if ($playerId > 0) {
                $rows[$playerId] ??= $this->blankPlayerRow($player);
                $rows[$playerId]['appearances'][$event->match_id] = true;
            }

            if ($type === 'goal' || $type === 'penalty_goal') {
                if ($playerId > 0) {
                    $rows[$playerId][$type === 'penalty_goal' ? 'penalty_goals' : 'goals']++;
                }

                if ($assistPlayer) {
                    $assistId = (int) $assistPlayer->id;
                    $rows[$assistId] ??= $this->blankPlayerRow($assistPlayer);
                    $rows[$assistId]['assists']++;
                    $rows[$assistId]['appearances'][$event->match_id] = true;
                }
                continue;
            }

            if ($type === 'assist') {
                if ($playerId > 0) {
                    $rows[$playerId]['assists']++;
                }
                continue;
            }

            if ($type === 'own_goal' && $playerId > 0) {
                $rows[$playerId]['own_goals']++;
                continue;
            }

            if ($type === 'yellow_card' && $playerId > 0) {
                $rows[$playerId]['yellow_cards']++;
                continue;
            }

            if ($type === 'red_card' && $playerId > 0) {
                $rows[$playerId]['red_cards']++;
                continue;
            }

            if ($type === 'penalty_miss' && $playerId > 0) {
                $rows[$playerId]['penalty_misses']++;
            }
        }

        foreach ($rows as &$row) {
            $row['appearances'] = count($row['appearances']);
            $row['discipline_points'] = ($row['yellow_cards'] * 1) + ($row['red_cards'] * 3);
        }
        unset($row);

        return $this->rankPlayerStats($rows);
    }

    /**
     * @param  Collection<int, SportMatch>  $matches
     * @param  Collection<int, SportMatchEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function calculateTeamStats(Collection $matches, Collection $events): array
    {
        $rows = [];

        foreach ($matches as $match) {
            if (! $this->isCompletedMatch($match)) {
                continue;
            }

            $homeTeamId = (int) $match->home_team_id;
            $awayTeamId = (int) $match->away_team_id;
            $homeGoals = (int) $match->home_score;
            $awayGoals = (int) $match->away_score;

            $rows[$homeTeamId] ??= $this->blankTeamRow($match->homeTeam);
            $rows[$awayTeamId] ??= $this->blankTeamRow($match->awayTeam);

            $rows[$homeTeamId]['played']++;
            $rows[$awayTeamId]['played']++;
            $rows[$homeTeamId]['goals_for'] += $homeGoals;
            $rows[$homeTeamId]['goals_against'] += $awayGoals;
            $rows[$awayTeamId]['goals_for'] += $awayGoals;
            $rows[$awayTeamId]['goals_against'] += $homeGoals;

            if ($awayGoals === 0) {
                $rows[$homeTeamId]['clean_sheets']++;
            }

            if ($homeGoals === 0) {
                $rows[$awayTeamId]['clean_sheets']++;
            }

            if ($homeGoals > $awayGoals) {
                $rows[$homeTeamId]['wins']++;
                $rows[$awayTeamId]['losses']++;
                continue;
            }

            if ($awayGoals > $homeGoals) {
                $rows[$awayTeamId]['wins']++;
                $rows[$homeTeamId]['losses']++;
                continue;
            }

            $rows[$homeTeamId]['draws']++;
            $rows[$awayTeamId]['draws']++;
        }

        foreach ($events as $event) {
            $teamId = (int) ($event->team_id ?? 0);
            $type = $this->normalizeEventType($event->event_type);

            if (! isset($rows[$teamId])) {
                $rows[$teamId] = $this->blankTeamRow($event->team);
            }

            if ($type === 'yellow_card') {
                $rows[$teamId]['yellow_cards']++;
            } elseif ($type === 'red_card') {
                $rows[$teamId]['red_cards']++;
            }
        }

        foreach ($rows as &$row) {
            $row['cards'] = $row['yellow_cards'] + $row['red_cards'];
            $row['fair_play_points'] = ($row['yellow_cards'] * 1) + ($row['red_cards'] * 3);
            $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
        }
        unset($row);

        return $this->rankTeamStats($rows);
    }

    /**
     * @param  Collection<int, SportMatch>  $matches
     * @param  Collection<int, SportMatchEvent>  $events
     * @param  array<int, array<string, mixed>>  $playerStats
     * @param  array<int, array<string, mixed>>  $teamStats
     * @return array<string, mixed>
     */
    private function calculateTournamentSummary(Collection $matches, Collection $events, array $playerStats, array $teamStats): array
    {
        $completedMatches = $matches->filter(fn (SportMatch $match): bool => $this->isCompletedMatch($match));
        $scheduledMatches = $matches->filter(fn (SportMatch $match): bool => in_array($match->status, ['scheduled', 'live', 'halftime', 'postponed'], true));
        $totalGoals = (int) $completedMatches->sum(fn (SportMatch $match): int => (int) $match->home_score + (int) $match->away_score);
        $totalYellowCards = (int) $events->filter(fn (SportMatchEvent $event): bool => $this->normalizeEventType($event->event_type) === 'yellow_card')->count();
        $totalRedCards = (int) $events->filter(fn (SportMatchEvent $event): bool => $this->normalizeEventType($event->event_type) === 'red_card')->count();

        $topScorer = collect($playerStats)->sort(function (array $left, array $right): int {
            if ($left['goals'] !== $right['goals']) {
                return $right['goals'] <=> $left['goals'];
            }

            if ($left['assists'] !== $right['assists']) {
                return $right['assists'] <=> $left['assists'];
            }

            return strcasecmp((string) $left['player_name'], (string) $right['player_name']);
        })->first();

        $topAssistProvider = collect($playerStats)->sort(function (array $left, array $right): int {
            if ($left['assists'] !== $right['assists']) {
                return $right['assists'] <=> $left['assists'];
            }

            if ($left['goals'] !== $right['goals']) {
                return $right['goals'] <=> $left['goals'];
            }

            return strcasecmp((string) $left['player_name'], (string) $right['player_name']);
        })->first();

        $bestAttack = collect($teamStats)->sort(function (array $left, array $right): int {
            if ($left['goals_for'] !== $right['goals_for']) {
                return $right['goals_for'] <=> $left['goals_for'];
            }

            if ($left['goal_difference'] !== $right['goal_difference']) {
                return $right['goal_difference'] <=> $left['goal_difference'];
            }

            return strcasecmp((string) $left['team_name'], (string) $right['team_name']);
        })->first();

        $bestDefense = collect($teamStats)->sort(function (array $left, array $right): int {
            if ($left['goals_against'] !== $right['goals_against']) {
                return $left['goals_against'] <=> $right['goals_against'];
            }

            if ($left['clean_sheets'] !== $right['clean_sheets']) {
                return $right['clean_sheets'] <=> $left['clean_sheets'];
            }

            return strcasecmp((string) $left['team_name'], (string) $right['team_name']);
        })->first();

        $fairPlayLeader = collect($teamStats)->sort(function (array $left, array $right): int {
            if ($left['fair_play_points'] !== $right['fair_play_points']) {
                return $left['fair_play_points'] <=> $right['fair_play_points'];
            }

            return strcasecmp((string) $left['team_name'], (string) $right['team_name']);
        })->first();

        return [
            'total_matches' => $matches->count(),
            'completed_matches' => $completedMatches->count(),
            'scheduled_matches' => $scheduledMatches->count(),
            'total_goals' => $totalGoals,
            'goals_per_match' => $completedMatches->count() > 0 ? round($totalGoals / max(1, $completedMatches->count()), 2) : 0,
            'total_yellow_cards' => $totalYellowCards,
            'total_red_cards' => $totalRedCards,
            'top_scorer' => $topScorer,
            'top_assist_provider' => $topAssistProvider,
            'best_attack' => $bestAttack,
            'best_defense' => $bestDefense,
            'fair_play_leader' => $fairPlayLeader,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rankPlayerStats(array $rows): array
    {
        return collect($rows)
            ->sort(function (array $left, array $right): int {
                if ($left['goals'] !== $right['goals']) {
                    return $right['goals'] <=> $left['goals'];
                }

                if ($left['assists'] !== $right['assists']) {
                    return $right['assists'] <=> $left['assists'];
                }

                return strcasecmp((string) $left['player_name'], (string) $right['player_name']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rankTeamStats(array $rows): array
    {
        return collect($rows)
            ->sort(function (array $left, array $right): int {
                if ($left['goals_for'] !== $right['goals_for']) {
                    return $right['goals_for'] <=> $left['goals_for'];
                }

                if ($left['goals_against'] !== $right['goals_against']) {
                    return $left['goals_against'] <=> $right['goals_against'];
                }

                if ($left['clean_sheets'] !== $right['clean_sheets']) {
                    return $right['clean_sheets'] <=> $left['clean_sheets'];
                }

                return strcasecmp((string) $left['team_name'], (string) $right['team_name']);
            })
            ->values()
            ->all();
    }

    private function blankPlayerRow(?SportPlayer $player): array
    {
        return [
            'player_id' => (int) ($player?->id ?? 0),
            'player_name' => trim(($player?->first_name ?? '').' '.($player?->last_name ?? '')),
            'team_id' => (int) ($player?->team_id ?? 0),
            'team_name' => $player?->team?->name ?? '',
            'goals' => 0,
            'assists' => 0,
            'penalty_goals' => 0,
            'penalty_misses' => 0,
            'own_goals' => 0,
            'yellow_cards' => 0,
            'red_cards' => 0,
            'discipline_points' => 0,
            'appearances' => [],
        ];
    }

    private function blankTeamRow(?SportTeam $team): array
    {
        return [
            'team_id' => (int) ($team?->id ?? 0),
            'team_name' => (string) ($team?->name ?? ''),
            'played' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'goal_difference' => 0,
            'clean_sheets' => 0,
            'yellow_cards' => 0,
            'red_cards' => 0,
            'cards' => 0,
            'fair_play_points' => 0,
        ];
    }

    private function isCompletedMatch(SportMatch $match): bool
    {
        return in_array($match->status, ['completed'], true);
    }

    private function normalizeEventType(string $eventType): string
    {
        return strtolower(trim($eventType));
    }
}
