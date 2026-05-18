<?php

namespace App\Support\SportTournament;

use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TournamentService
{
    public function create(array $data, ?User $user = null): SportTournament
    {
        $tournament = SportTournament::query()->create($this->normalizeTournamentData($data, $user));

        return $tournament->refresh();
    }

    public function update(SportTournament $tournament, array $data): SportTournament
    {
        $tournament->fill($this->normalizeTournamentData($data, $tournament, false));
        $tournament->save();

        return $tournament->refresh();
    }

    public function archive(SportTournament $tournament): SportTournament
    {
        $tournament->status = 'archived';
        $tournament->save();

        return $tournament->refresh();
    }

    /**
     * @param  array<int, int|string>  $teamIds
     * @return array<int, SportTeam>
     */
    public function attachTeams(SportTournament $tournament, array $teamIds): array
    {
        $resolvedTeams = SportTeam::query()->whereIn('id', $teamIds)->get();
        $now = Carbon::now();

        $syncData = $resolvedTeams->mapWithKeys(fn (SportTeam $team): array => [
            $team->id => ['joined_at' => $now],
        ])->all();

        if ($syncData !== []) {
            $tournament->teams()->syncWithoutDetaching($syncData);
        }

        return $resolvedTeams->all();
    }

    public function detachTeam(SportTournament $tournament, int|string $teamId): void
    {
        $tournament->teams()->detach($teamId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normalizeTournamentData(array $data, User|SportTournament|null $context = null, bool $creating = true): array
    {
        $tournamentCode = trim((string) ($data['tournament_code'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $existingTournament = $context instanceof SportTournament ? $context : null;
        $creator = $context instanceof User ? $context : null;

        return [
            'tournament_code' => $tournamentCode !== '' ? $tournamentCode : ($creating ? $this->makeCode('tournament') : null),
            'slug' => $slug !== '' ? Str::slug($slug) : null,
            'name' => (string) ($data['name'] ?? ''),
            'season' => $data['season'] ?? null,
            'tournament_type' => $data['tournament_type'] ?? ($data['sport_type'] ?? 'league'),
            'status' => $data['status'] ?? 'draft',
            'visibility' => $data['visibility'] ?? 'private',
            'registration_open_at' => $data['registration_open_at'] ?? null,
            'registration_close_at' => $data['registration_close_at'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'description' => $data['description'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'banner_path' => $data['banner_path'] ?? null,
            'location' => $data['location'] ?? null,
            'organizer' => $data['organizer'] ?? null,
            'rules' => $data['rules'] ?? null,
            'settings' => $data['settings'] ?? null,
            'created_by_user_id' => $creating
                ? $creator?->id
                : ($data['created_by_user_id'] ?? $existingTournament?->created_by_user_id ?? null),
        ];
    }

    private function makeCode(string $prefix): string
    {
        return strtoupper($prefix.'-'.Str::random(8));
    }
}
