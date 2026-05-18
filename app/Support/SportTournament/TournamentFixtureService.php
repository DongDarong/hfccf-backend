<?php

namespace App\Support\SportTournament;

use App\Models\SportMatch;
use App\Models\SportTournament;
use App\Models\SportTournamentGroup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TournamentFixtureService
{
    /**
     * Generate group-stage fixtures from finalized groups.
     *
     * The service keeps the write model small: tournament metadata lives on the
     * match row while the pairing metadata stays in JSON for future expansion.
     *
     * @return EloquentCollection<int, SportMatch>
     */
    public function generate(int|SportTournament $tournament, array $options = []): EloquentCollection
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament->loadMissing(['groups.groupTeams.team'])
            : SportTournament::query()->with(['groups.groupTeams.team'])->findOrFail($tournament);

        $replaceExisting = (bool) ($options['replace'] ?? true);
        $doubleRoundRobin = (bool) ($options['double_round_robin'] ?? ($resolvedTournament->settings['double_round_robin'] ?? false));

        $groups = $resolvedTournament->groups->sortBy(fn (SportTournamentGroup $group): int => (int) $group->position)->values();
        if ($groups->isEmpty()) {
            throw new \RuntimeException('Tournament groups must be drawn before generating fixtures.');
        }

        if ($groups->contains(fn (SportTournamentGroup $group): bool => $group->status !== 'finalized')) {
            throw new \RuntimeException('Tournament groups must be finalized before generating fixtures.');
        }

        if ($replaceExisting) {
            $resolvedTournament->matches()
                ->whereNotNull('group_id')
                ->whereNull('knockout_round_id')
                ->delete();
        }

        $matchday = 1;

        foreach ($groups as $group) {
            $this->generateGroupFixtures($resolvedTournament, $group, $matchday, $doubleRoundRobin);
            $matchday += max(1, $group->groupTeams->count() - 1);
        }

        $this->markTournamentAsHavingFixtures($resolvedTournament);

        return SportMatch::query()
            ->with(['homeTeam', 'awayTeam', 'group', 'tournament'])
            ->where('tournament_id', $resolvedTournament->id)
            ->whereNull('knockout_round_id')
            ->orderBy('group_id')
            ->orderBy('matchday')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }

    public function list(int|SportTournament $tournament): EloquentCollection
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament
            : SportTournament::query()->findOrFail($tournament);

        return SportMatch::query()
            ->with(['homeTeam', 'awayTeam', 'group', 'knockoutRound', 'tournament'])
            ->where('tournament_id', $resolvedTournament->id)
            ->orderByRaw('group_id is null')
            ->orderBy('group_id')
            ->orderBy('matchday')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, SportMatch>
     */
    private function generateGroupFixtures(SportTournament $tournament, SportTournamentGroup $group, int &$matchday, bool $doubleRoundRobin): EloquentCollection
    {
        $teamIds = $group->groupTeams
            ->sortBy(fn ($groupTeam): string => sprintf(
                '%012d|%012d|%012d',
                (int) ($groupTeam->position ?? PHP_INT_MAX),
                (int) ($groupTeam->seed ?? PHP_INT_MAX),
                (int) ($groupTeam->team_id ?? PHP_INT_MAX),
            ))
            ->pluck('team_id')
            ->map(fn ($teamId): int => (int) $teamId)
            ->values()
            ->all();

        if (count($teamIds) < 2) {
            return new EloquentCollection();
        }

        $pairs = $this->buildPairs($teamIds);
        $created = [];
        $leg = 1;

        foreach ($pairs as [$homeTeamId, $awayTeamId]) {
            $created[] = $this->createFixture($tournament, $group, $homeTeamId, $awayTeamId, $matchday, $leg, 1);
            $matchday++;

            if ($doubleRoundRobin) {
                $created[] = $this->createFixture($tournament, $group, $awayTeamId, $homeTeamId, $matchday, $leg, 2);
                $matchday++;
            }

            $leg++;
        }

        return new EloquentCollection($created);
    }

    /**
     * @return array<int, array{0:int,1:int}>
     */
    private function buildPairs(array $teamIds): array
    {
        $pairs = [];
        $count = count($teamIds);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairs[] = [$teamIds[$i], $teamIds[$j]];
            }
        }

        return $pairs;
    }

    private function createFixture(
        SportTournament $tournament,
        SportTournamentGroup $group,
        int $homeTeamId,
        int $awayTeamId,
        int $matchday,
        int $pairIndex,
        int $leg,
    ): SportMatch {
        $match = SportMatch::query()->create([
            'match_code' => $this->makeMatchCode(),
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'tournament_id' => $tournament->id,
            'group_id' => $group->id,
            'competition_type' => 'tournament',
            'tournament_name' => $tournament->name,
            'round_name' => $group->name,
            'matchday' => $matchday,
            'venue' => $group->metadata['venue'] ?? $tournament->location,
            'scheduled_at' => $this->resolveFixtureDate($tournament, $matchday, $pairIndex),
            'status' => 'scheduled',
            'current_period' => null,
            'home_score' => 0,
            'away_score' => 0,
            'extra_time_home_score' => 0,
            'extra_time_away_score' => 0,
            'penalty_home_score' => 0,
            'penalty_away_score' => 0,
            'winner_team_id' => null,
            'metadata' => [
                'stage' => 'group',
                'group_id' => $group->id,
                'group_code' => $group->code,
                'pair_index' => $pairIndex,
                'leg' => $leg,
            ],
            'notes' => null,
            'created_by_user_id' => $tournament->created_by_user_id,
        ]);

        return $match;
    }

    private function resolveFixtureDate(SportTournament $tournament, int $matchday, int $pairIndex): ?Carbon
    {
        $baseDate = $tournament->starts_at ?? now();

        return $baseDate
            ? Carbon::parse($baseDate)->addDays(max(0, $matchday - 1))->addHours($pairIndex)
            : null;
    }

    private function markTournamentAsHavingFixtures(SportTournament $tournament): void
    {
        if (in_array($tournament->status, ['group_draw_completed', 'registration_closed', 'draft'], true)) {
            $tournament->status = 'fixtures_generated';
            $tournament->save();
        }
    }

    private function makeMatchCode(): string
    {
        return strtoupper('match-'.Str::random(8));
    }
}
