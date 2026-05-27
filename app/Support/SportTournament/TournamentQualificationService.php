<?php

namespace App\Support\SportTournament;

use App\Models\SportTournament;

class TournamentQualificationService
{
    public function __construct(private readonly TournamentStandingsService $standingsService) {}

    /**
     * Build the set of qualifiers from the calculated standings.
     *
     * The rules intentionally stay dynamic so the frontend can change draw
     * settings without needing a second source of truth.
     *
     * @return array{
     *   groups: array<int, array<string, mixed>>,
     *   qualifiers: array<int, array<string, mixed>>
     * }
     */
    public function calculate(int|SportTournament $tournament): array
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament
            : SportTournament::query()->findOrFail($tournament);

        $calculated = $this->standingsService->calculate($resolvedTournament);
        $groupSummaries = $calculated['groups'];
        $settings = $resolvedTournament->settings ?? [];
        $rules = $resolvedTournament->rules ?? [];

        $perGroupSlots = (int) ($settings['qualification_slots'] ?? $rules['qualification_slots'] ?? 0);
        $bestThirdPlaceCount = (int) ($settings['best_third_place_teams'] ?? $rules['best_third_place_teams'] ?? 0);

        $qualifiers = collect();

        foreach ($groupSummaries as $groupSummary) {
            $groupStandings = collect($groupSummary['standings'] ?? []);
            $slots = (int) ($groupSummary['group']->qualification_slots ?? 0);
            $slots = $slots > 0 ? $slots : $perGroupSlots;

            $groupQualifiers = $groupStandings
                ->take(max(0, $slots))
                ->map(function (array $row) use ($groupSummary): array {
                    return $this->normalizeQualifier($row, (string) $groupSummary['group']->code, (string) $groupSummary['group']->name);
                });

            $qualifiers = $qualifiers->merge($groupQualifiers);
        }

        if ($bestThirdPlaceCount > 0) {
            $thirdPlaceCandidates = collect($groupSummaries)
                ->map(fn (array $groupSummary): ?array => $this->thirdPlaceCandidate($groupSummary))
                ->filter()
                ->sort(function (array $left, array $right): int {
                    foreach (['points', 'goal_difference', 'goals_for'] as $field) {
                        if ($left[$field] !== $right[$field]) {
                            return $right[$field] <=> $left[$field];
                        }
                    }

                    return strcasecmp((string) $left['team_name'], (string) $right['team_name']);
                })
                ->take($bestThirdPlaceCount);

            $qualifiers = $qualifiers->merge($thirdPlaceCandidates);
        }

        return [
            'groups' => $groupSummaries,
            'qualifiers' => $qualifiers
                ->unique('team_id')
                ->values()
                ->all(),
        ];
    }

    private function normalizeQualifier(array $row, string $groupCode, string $groupName): array
    {
        $row['group_code'] = $groupCode;
        $row['group_name'] = $groupName;
        $row['qualified'] = true;

        return $row;
    }

    private function thirdPlaceCandidate(array $groupSummary): ?array
    {
        $standings = collect($groupSummary['standings'] ?? []);

        if ($standings->count() < 3) {
            return null;
        }

        $third = $standings->get(2);
        if (! is_array($third)) {
            return null;
        }

        return $this->normalizeQualifier($third, (string) $groupSummary['group']->code, (string) $groupSummary['group']->name);
    }
}
