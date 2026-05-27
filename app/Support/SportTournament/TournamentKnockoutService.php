<?php

namespace App\Support\SportTournament;

use App\Models\SportMatch;
use App\Models\SportTournament;
use App\Models\SportTournamentKnockoutRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TournamentKnockoutService
{
    public function __construct(
        private readonly TournamentQualificationService $qualificationService,
    ) {}

    /**
     * Create a knockout bracket from the currently qualified teams.
     *
     * We store the bracket as rounds plus matches, but the advancing teams stay
     * on the match rows so the bracket can be recalculated at any point.
     *
     * @return array{
     *   tournament: SportTournament,
     *   rounds: EloquentCollection<int, SportTournamentKnockoutRound>,
     *   matches: EloquentCollection<int, SportMatch>
     * }
     */
    public function generate(int|SportTournament $tournament, array $options = []): array
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament->loadMissing(['groups.groupTeams.team', 'matches', 'knockoutRounds'])
            : SportTournament::query()->with(['groups.groupTeams.team', 'matches', 'knockoutRounds'])->findOrFail($tournament);

        if (! in_array($resolvedTournament->status, ['group_draw_completed', 'fixtures_generated', 'active', 'knockout_stage'], true)) {
            throw new \RuntimeException('Knockout bracket can only be generated after group standings are available.');
        }

        $qualification = $this->qualificationService->calculate($resolvedTournament);
        $qualifiers = collect($qualification['qualifiers'] ?? []);
        $qualifierCount = $qualifiers->count();

        if (! in_array($qualifierCount, [4, 8, 16], true)) {
            throw new \RuntimeException('Knockout bracket requires 4, 8, or 16 qualified teams.');
        }

        $replaceExisting = (bool) ($options['replace'] ?? true);

        if ($replaceExisting) {
            $resolvedTournament->knockoutRounds()->delete();
            $resolvedTournament->matches()
                ->whereNotNull('knockout_round_id')
                ->delete();
        }

        $roundSizes = $this->buildRoundSizes($qualifierCount);
        $rounds = collect();
        $previousRoundMatches = new EloquentCollection;

        foreach ($roundSizes as $index => $matchCount) {
            $roundPosition = $index + 1;
            $roundName = $this->roundNameForMatchCount($matchCount);

            $round = SportTournamentKnockoutRound::query()->create([
                'tournament_id' => $resolvedTournament->id,
                'name' => $roundName,
                'code' => $this->makeCode('round'),
                'position' => $roundPosition,
                'bracket_size' => $matchCount,
                'status' => 'draft',
                'completed_at' => null,
                'metadata' => [
                    'round_size' => $matchCount,
                ],
            ]);

            $rounds->push($round);

            $roundMatches = $this->createRoundMatches($resolvedTournament, $round, $qualifiers, $previousRoundMatches, $matchCount);
            $previousRoundMatches = $roundMatches;
        }

        $resolvedTournament->status = 'knockout_stage';
        $resolvedTournament->save();

        return [
            'tournament' => $resolvedTournament->refresh(),
            'rounds' => $rounds->values(),
            'matches' => SportMatch::query()
                ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'knockoutRound'])
                ->where('tournament_id', $resolvedTournament->id)
                ->whereNotNull('knockout_round_id')
                ->orderBy('id')
                ->get(),
        ];
    }

    public function propagateWinner(SportMatch $match, ?User $user = null): void
    {
        if (! $match->knockout_round_id || ! $match->winner_team_id) {
            return;
        }

        $knockoutRound = SportTournamentKnockoutRound::query()
            ->with(['tournament', 'matches'])
            ->find($match->knockout_round_id);

        if (! $knockoutRound) {
            return;
        }

        $nextRound = SportTournamentKnockoutRound::query()
            ->where('tournament_id', $knockoutRound->tournament_id)
            ->where('position', '>', $knockoutRound->position)
            ->orderBy('position')
            ->first();

        if (! $nextRound) {
            $knockoutRound->status = 'completed';
            $knockoutRound->completed_at = Carbon::now();
            $knockoutRound->save();

            $tournament = $knockoutRound->tournament;
            if ($tournament) {
                $tournament->status = 'completed';
                $tournament->save();
            }

            return;
        }

        $nextRoundMatches = SportMatch::query()
            ->where('knockout_round_id', $nextRound->id)
            ->orderBy('id')
            ->get();

        foreach ($nextRoundMatches as $nextMatch) {
            $metadata = $nextMatch->metadata ?? [];
            $sourceMatchIds = array_map('intval', (array) ($metadata['source_match_ids'] ?? []));

            if (! in_array((int) $match->id, $sourceMatchIds, true)) {
                continue;
            }

            if (empty($nextMatch->home_team_id)) {
                $nextMatch->home_team_id = (int) $match->winner_team_id;
            } elseif (empty($nextMatch->away_team_id)) {
                $nextMatch->away_team_id = (int) $match->winner_team_id;
            }

            $nextMatch->save();
            break;
        }
    }

    /**
     * @return array<int, int>
     */
    private function buildRoundSizes(int $qualifierCount): array
    {
        $sizes = [];
        $currentSize = $qualifierCount / 2;

        while ($currentSize >= 1) {
            $sizes[] = (int) $currentSize;
            $currentSize /= 2;
        }

        return $sizes;
    }

    private function roundNameForMatchCount(int $matchCount): string
    {
        return match ($matchCount) {
            8 => 'Round of 16',
            4 => 'Quarterfinal',
            2 => 'Semifinal',
            1 => 'Final',
            default => 'Knockout',
        };
    }

    /**
     * @return EloquentCollection<int, SportMatch>
     */
    private function createRoundMatches(
        SportTournament $tournament,
        SportTournamentKnockoutRound $round,
        Collection $qualifiers,
        EloquentCollection $previousRoundMatches,
        int $matchCount,
    ): EloquentCollection {
        $created = collect();

        if ($round->position === 1) {
            $pairs = $this->pairQualifiers($qualifiers->all());

            foreach ($pairs as $index => [$home, $away]) {
                $match = $this->createKnockoutMatch(
                    $tournament,
                    $round,
                    (int) $home['team_id'],
                    (int) $away['team_id'],
                    $index + 1,
                    [
                        'seed_home' => $home,
                        'seed_away' => $away,
                    ],
                );

                $created->push($match);
            }

            return new EloquentCollection($created->all());
        }

        $previousMatches = $previousRoundMatches->values();
        $slots = max(1, (int) ($previousMatches->count() / 2));

        for ($index = 0; $index < $slots; $index++) {
            $sourceA = $previousMatches->get($index * 2);
            $sourceB = $previousMatches->get($index * 2 + 1);

            $match = $this->createKnockoutMatch(
                $tournament,
                $round,
                null,
                null,
                $index + 1,
                [
                    'source_match_ids' => array_values(array_filter([
                        $sourceA?->id,
                        $sourceB?->id,
                    ])),
                    'source_match_codes' => array_values(array_filter([
                        $sourceA?->match_code,
                        $sourceB?->match_code,
                    ])),
                ],
            );

            $created->push($match);
        }

        return new EloquentCollection($created->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $qualifiers
     * @return array<int, array{0:array<string, mixed>,1:array<string, mixed>}>
     */
    private function pairQualifiers(array $qualifiers): array
    {
        $pairs = [];
        $left = 0;
        $right = count($qualifiers) - 1;

        while ($left < $right) {
            $home = $qualifiers[$left];
            $away = $qualifiers[$right];

            if (($home['group_code'] ?? null) === ($away['group_code'] ?? null) && ($right - $left) > 1) {
                $swapIndex = $right - 1;
                $altAway = $qualifiers[$swapIndex];
                if (($home['group_code'] ?? null) !== ($altAway['group_code'] ?? null)) {
                    $away = $altAway;
                    $qualifiers[$swapIndex] = $qualifiers[$right];
                }
            }

            $pairs[] = [$home, $away];
            $left++;
            $right--;
        }

        return $pairs;
    }

    private function createKnockoutMatch(
        SportTournament $tournament,
        SportTournamentKnockoutRound $round,
        ?int $homeTeamId,
        ?int $awayTeamId,
        int $matchNumber,
        array $metadata = [],
    ): SportMatch {
        return SportMatch::query()->create([
            'match_code' => $this->makeCode('match'),
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'tournament_id' => $tournament->id,
            'knockout_round_id' => $round->id,
            'competition_type' => 'tournament',
            'tournament_name' => $tournament->name,
            'round_name' => $round->name,
            'matchday' => $round->position,
            'venue' => $tournament->location,
            'scheduled_at' => $tournament->starts_at,
            'status' => 'scheduled',
            'current_period' => null,
            'home_score' => 0,
            'away_score' => 0,
            'extra_time_home_score' => 0,
            'extra_time_away_score' => 0,
            'penalty_home_score' => 0,
            'penalty_away_score' => 0,
            'winner_team_id' => null,
            'metadata' => array_merge([
                'stage' => 'knockout',
                'round_code' => $round->code,
                'match_number' => $matchNumber,
            ], $metadata),
            'created_by_user_id' => $tournament->created_by_user_id,
        ]);
    }

    private function makeCode(string $prefix): string
    {
        return strtoupper($prefix.'-'.Str::random(8));
    }
}
