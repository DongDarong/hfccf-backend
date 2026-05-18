<?php

namespace Tests\Feature;

use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\SportTournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportTournamentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_tournament_with_foundation_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/sport/tournaments', [
            'tournament_code' => 'TRN-FOUND-001',
            'slug' => 'foundation-cup-2026',
            'name' => 'Foundation Cup 2026',
            'season' => '2026',
            'tournament_type' => 'league',
            'visibility' => 'public',
            'status' => 'registration_closed',
            'registration_open_at' => '2026-05-01 08:00:00',
            'registration_close_at' => '2026-05-10 08:00:00',
            'starts_at' => '2026-05-15 08:00:00',
            'ends_at' => '2026-06-15 08:00:00',
            'description' => 'Tournament foundation test',
            'location' => 'National Stadium',
            'organizer' => 'HFCCF Sports',
            'rules' => [
                'group_count' => 2,
                'qualification_slots' => 2,
            ],
            'settings' => [
                'double_round_robin' => false,
                'best_third_place_teams' => 0,
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tournament.slug', 'foundation-cup-2026')
            ->assertJsonPath('data.tournament.visibility', 'public');
    }

    public function test_group_draw_and_finalization_create_group_assignments(): void
    {
        $this->actingAsAdmin();
        $tournament = $this->createTournament('TRN-FOUND-UNAUTH', 'Unauth Cup', 'registration_closed', null);
        $teams = $this->createTournamentTeams($tournament, 4);

        $draw = $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/draw', [
            'group_count' => 2,
            'qualification_slots' => 2,
        ]);

        $draw
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('sport_tournament_groups', 2);
        $this->assertDatabaseCount('sport_tournament_group_teams', 4);

        $finalize = $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/finalize');

        $finalize
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('sport_tournament_groups', [
            'tournament_id' => $tournament->id,
            'status' => 'finalized',
        ]);
    }

    public function test_fixture_generation_requires_finalized_groups_and_creates_matches(): void
    {
        $this->actingAsAdmin();
        $tournament = $this->createTournament();
        $this->createTournamentTeams($tournament, 4);

        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/draw', [
            'group_count' => 2,
            'qualification_slots' => 2,
        ])->assertOk();

        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/finalize')
            ->assertOk();

        $fixtures = $this->postJson('/api/sport/tournaments/'.$tournament->id.'/fixtures/generate', [
            'double_round_robin' => false,
            'replace' => true,
        ]);

        $fixtures
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('sport_matches', 2);
    }

    public function test_result_and_event_save_recalculates_score_standings_and_statistics(): void
    {
        $this->actingAsAdmin();
        $tournament = $this->createTournament();
        $teams = $this->createTournamentTeams($tournament, 4);
        $players = $this->createPlayersForTeams($teams);

        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/draw', [
            'group_count' => 2,
            'qualification_slots' => 2,
        ])->assertOk();

        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/finalize')->assertOk();
        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/fixtures/generate')->assertOk();

        $match = SportMatch::query()->where('tournament_id', $tournament->id)->whereNotNull('group_id')->orderBy('id')->firstOrFail();
        $homePlayer = $players[$match->home_team_id][0];

        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/results/'.$match->id.'/events', [
            'team_id' => $match->home_team_id,
            'player_id' => $homePlayer->id,
            'event_type' => 'goal',
            'minute' => 11,
            'side' => 'home',
            'description' => 'Opening goal',
        ])->assertCreated();

        $saveResult = $this->putJson('/api/sport/tournaments/'.$tournament->id.'/results/'.$match->id, [
            'home_score' => 1,
            'away_score' => 0,
            'status' => 'completed',
            'current_period' => 'final',
        ]);

        $saveResult
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.match.homeScore', 1)
            ->assertJsonPath('data.standings.0.points', 3);

        $stats = $this->getJson('/api/sport/tournaments/'.$tournament->id.'/statistics');
        $stats
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statistics.summary.total_goals', 1);
    }

    public function test_knockout_generation_requires_valid_qualifier_count_and_generates_bracket(): void
    {
        $this->actingAsAdmin();
        $tournament = $this->createTournament();
        $this->createTournamentTeams($tournament, 4);

        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/draw', [
            'group_count' => 2,
            'qualification_slots' => 2,
        ])->assertOk();
        $this->postJson('/api/sport/tournaments/'.$tournament->id.'/groups/finalize')->assertOk();

        $knockout = $this->postJson('/api/sport/tournaments/'.$tournament->id.'/knockout/generate', [
            'replace' => true,
        ]);

        $knockout
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('sport_tournament_knockout_rounds', 2);

        $invalidTournament = $this->createTournament('TRN-FOUND-INVALID', 'Invalid Cup', 'registration_closed');
        $this->createTournamentTeams($invalidTournament, 3, 'INVALID');
        $this->postJson('/api/sport/tournaments/'.$invalidTournament->id.'/groups/draw', [
            'group_count' => 1,
            'qualification_slots' => 1,
        ])->assertOk();
        $this->postJson('/api/sport/tournaments/'.$invalidTournament->id.'/groups/finalize')->assertOk();

        $this->postJson('/api/sport/tournaments/'.$invalidTournament->id.'/knockout/generate', [
            'replace' => true,
        ])->assertUnprocessable();
    }

    public function test_unauthorized_users_are_blocked_from_tournament_foundation_routes(): void
    {
        $tournament = $this->createTournament('TRN-FOUND-UNAUTH', 'Unauth Cup', 'registration_closed', null);

        $this->getJson('/api/sport/tournaments/'.$tournament->id.'/groups')
            ->assertUnauthorized();
    }

    private function actingAsAdmin(): User
    {
        $user = User::query()->create([
            'id' => 'usr-foundation-admin',
            'first_name' => 'Admin',
            'last_name' => 'Sport',
            'username' => 'Admin Sport',
            'email' => 'admin.foundation@hfccf.test',
            'role_code' => 'adminsport',
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createTournament(string $code = 'TRN-FOUND-001', string $name = 'Foundation Cup', string $status = 'registration_closed', ?string $createdByUserId = 'usr-foundation-admin'): SportTournament
    {
        return SportTournament::query()->create([
            'tournament_code' => $code,
            'slug' => $code,
            'name' => $name,
            'season' => '2026',
            'tournament_type' => 'league',
            'status' => $status,
            'visibility' => 'public',
            'description' => 'Tournament foundation test',
            'rules' => [
                'group_count' => 2,
                'qualification_slots' => 2,
            ],
            'settings' => [
                'double_round_robin' => false,
                'best_third_place_teams' => 0,
            ],
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    /**
     * @return array<int, SportTeam>
     */
    private function createTournamentTeams(SportTournament $tournament, int $count, string $prefix = 'FOUND'): array
    {
        $teams = [];

        for ($index = 1; $index <= $count; $index++) {
            $team = SportTeam::query()->create([
                'team_code' => sprintf('TEAM-%s-%03d', strtoupper($prefix), $index),
                'name' => sprintf('%s Team %d', ucfirst(strtolower($prefix)), $index),
                'short_name' => sprintf('%s%d', strtoupper(substr($prefix, 0, 2)), $index),
                'status' => 'active',
            ]);

            $tournament->teams()->syncWithoutDetaching([
                $team->id => ['joined_at' => now()],
            ]);

            $teams[] = $team;
        }

        return $teams;
    }

    /**
     * @param  array<int, SportTeam>  $teams
     * @return array<int, array<int, SportPlayer>>
     */
    private function createPlayersForTeams(array $teams): array
    {
        $players = [];

        foreach ($teams as $team) {
            $players[$team->id] = [];
            $players[$team->id][] = SportPlayer::query()->create([
                'player_code' => sprintf('PLY-%s-1', $team->id),
                'first_name' => 'Player',
                'last_name' => (string) $team->id,
                'jersey_number' => 9,
                'position' => 'Forward',
                'team_id' => $team->id,
                'status' => 'active',
            ]);

            $players[$team->id][] = SportPlayer::query()->create([
                'player_code' => sprintf('PLY-%s-2', $team->id),
                'first_name' => 'Assist',
                'last_name' => (string) $team->id,
                'jersey_number' => 10,
                'position' => 'Midfielder',
                'team_id' => $team->id,
                'status' => 'active',
            ]);
        }

        return $players;
    }
}
