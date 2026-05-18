<?php

namespace App\Support\SportTournament;

use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Models\SportTournamentGroup;
use App\Models\SportTournamentGroupTeam;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TournamentGroupService
{
    public function list(int|SportTournament $tournament): EloquentCollection
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament
            : SportTournament::query()->findOrFail($tournament);

        return SportTournamentGroup::query()
            ->with(['groupTeams.team', 'matches'])
            ->where('tournament_id', $resolvedTournament->id)
            ->orderBy('position')
            ->get();
    }

    /**
     * Draw teams into groups. Manual assignments may be passed as
     * [team_id => group_code] or [team_id => group_id].
     */
    public function draw(int|SportTournament $tournament, array $options = []): EloquentCollection
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament->loadMissing(['teams'])
            : SportTournament::query()->with(['teams'])->findOrFail($tournament);

        if (! in_array($resolvedTournament->status, ['registration_closed', 'group_draw_completed'], true)) {
            throw new \RuntimeException('Tournament groups can only be drawn after registration closes.');
        }

        $groupCount = max(1, (int) ($options['group_count'] ?? $resolvedTournament->settings['group_count'] ?? $resolvedTournament->rules['group_count'] ?? 1));
        $qualificationSlots = max(1, (int) ($options['qualification_slots'] ?? $resolvedTournament->settings['qualification_slots'] ?? $resolvedTournament->rules['qualification_slots'] ?? 1));
        $manualAssignments = (array) ($options['assignments'] ?? []);
        $reset = (bool) ($options['reset'] ?? true);

        if ($reset) {
            $resolvedTournament->groups()->delete();
            SportTournamentGroupTeam::query()->where('tournament_id', $resolvedTournament->id)->delete();
        }

        $teams = $resolvedTournament->teams
            ->sortBy(fn (SportTeam $team): string => sprintf('%s|%012d', mb_strtolower((string) $team->name), (int) $team->id))
            ->values();

        if ($teams->isEmpty()) {
            return new EloquentCollection;
        }

        $groups = $this->buildGroups($resolvedTournament, $groupCount, $qualificationSlots);
        $groupLookup = $groups->keyBy(fn (SportTournamentGroup $group): string => strtolower($group->code));
        $groupById = $groups->keyBy('id');

        $autoIndex = 0;

        foreach ($teams as $team) {
            $targetGroup = $this->resolveTargetGroup($manualAssignments, $groupLookup, $groupById, $team, $autoIndex, $groupCount);

            SportTournamentGroupTeam::query()->updateOrCreate([
                'tournament_id' => $resolvedTournament->id,
                'group_id' => $targetGroup->id,
                'team_id' => $team->id,
            ], [
                'seed' => $autoIndex + 1,
                'pot' => null,
                'position' => $targetGroup->groupTeams()->count() + 1,
                'status' => 'assigned',
                'metadata' => [
                    'assigned_at' => Carbon::now()->toISOString(),
                ],
            ]);

            $autoIndex++;
        }

        return $this->list($resolvedTournament);
    }

    public function finalize(int|SportTournament $tournament): EloquentCollection
    {
        $resolvedTournament = $tournament instanceof SportTournament
            ? $tournament->loadMissing(['groups.groupTeams'])
            : SportTournament::query()->with(['groups.groupTeams'])->findOrFail($tournament);

        $this->assertNoDuplicateTeams($resolvedTournament);

        foreach ($resolvedTournament->groups as $group) {
            $group->status = 'finalized';
            $group->finalized_at = Carbon::now();
            $group->save();
        }

        if (in_array($resolvedTournament->status, ['draft', 'registration_closed'], true)) {
            $resolvedTournament->status = 'group_draw_completed';
            $resolvedTournament->save();
        }

        return $this->list($resolvedTournament);
    }

    public function attachTeam(SportTournament $tournament, SportTournamentGroup $group, SportTeam $team, ?int $position = null): SportTournamentGroupTeam
    {
        return SportTournamentGroupTeam::query()->updateOrCreate([
            'tournament_id' => $tournament->id,
            'group_id' => $group->id,
            'team_id' => $team->id,
        ], [
            'position' => $position ?? ($group->groupTeams()->count() + 1),
            'status' => 'assigned',
            'metadata' => [],
        ]);
    }

    public function detachTeam(SportTournament $tournament, SportTournamentGroup $group, SportTeam $team): void
    {
        SportTournamentGroupTeam::query()
            ->where('tournament_id', $tournament->id)
            ->where('group_id', $group->id)
            ->where('team_id', $team->id)
            ->delete();
    }

    /**
     * @return EloquentCollection<int, SportTournamentGroup>
     */
    private function buildGroups(SportTournament $tournament, int $groupCount, int $qualificationSlots): EloquentCollection
    {
        $groups = collect();

        for ($index = 0; $index < $groupCount; $index++) {
            $code = Str::upper(chr(65 + $index));
            $groups->push(SportTournamentGroup::query()->create([
                'tournament_id' => $tournament->id,
                'name' => 'Group '.$code,
                'code' => $code,
                'position' => $index + 1,
                'qualification_slots' => $qualificationSlots,
                'status' => 'draft',
                'metadata' => [
                    'group_index' => $index + 1,
                ],
            ]));
        }

        return new EloquentCollection($groups->all());
    }

    /**
     * @param  array<int|string, string|int>  $manualAssignments
     */
    private function resolveTargetGroup(
        array $manualAssignments,
        Collection $groupLookup,
        Collection $groupById,
        SportTeam $team,
        int $autoIndex,
        int $groupCount,
    ): SportTournamentGroup {
        $groupReference = $manualAssignments[$team->id] ?? $manualAssignments[(string) $team->id] ?? null;

        if ($groupReference !== null) {
            if (is_numeric($groupReference) && $groupById->has((int) $groupReference)) {
                return $groupById->get((int) $groupReference);
            }

            $groupKey = strtolower(trim((string) $groupReference));
            if ($groupLookup->has($groupKey)) {
                return $groupLookup->get($groupKey);
            }
        }

        $groupIndex = $autoIndex % max(1, $groupCount);
        $code = strtolower(chr(65 + $groupIndex));

        return $groupLookup->get($code) ?? $groupById->first();
    }

    private function assertNoDuplicateTeams(SportTournament $tournament): void
    {
        $teamIds = SportTournamentGroupTeam::query()
            ->where('tournament_id', $tournament->id)
            ->pluck('team_id');

        if ($teamIds->count() !== $teamIds->unique()->count()) {
            throw new \RuntimeException('Duplicate teams cannot be finalized in different groups.');
        }
    }
}
