<?php

namespace Tests\Feature;

use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\SportPlayerMembershipService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportTeamRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_team_with_selected_players_and_sync_roster(): void
    {
        $admin = $this->createUser('adminsport', 'team-admin-1', 'team-admin-1@hfccf.org');
        $players = [
            $this->createEligiblePlayer('Team', 'Player One'),
            $this->createEligiblePlayer('Team', 'Player Two'),
        ];

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/sport/teams', [
            'name' => 'Phoenix FC',
            'status' => 'active',
            'player_ids' => array_map(static fn (SportPlayer $player) => $player->id, $players),
        ])->assertCreated();

        $teamId = $response->json('data.team.id');

        $response
            ->assertJsonPath('data.team.playersCount', 2)
            ->assertJsonPath('data.team.name', 'Phoenix FC');

        $this->assertDatabaseHas('sport_teams', [
            'id' => $teamId,
            'players_count' => 2,
        ]);

        foreach ($players as $player) {
            $this->assertDatabaseHas('sport_players', [
                'id' => $player->id,
                'team_id' => $teamId,
            ]);

            $this->assertDatabaseHas('sport_player_team_memberships', [
                'team_id' => $teamId,
                'player_id' => $player->id,
                'status' => 'active',
            ]);
        }
    }

    public function test_admin_can_update_team_roster_without_duplicate_active_memberships(): void
    {
        $admin = $this->createUser('superadmin', 'team-admin-2', 'team-admin-2@hfccf.org');
        $playerOne = $this->createEligiblePlayer('Roster', 'One');
        $playerTwo = $this->createEligiblePlayer('Roster', 'Two');
        $playerThree = $this->createEligiblePlayer('Roster', 'Three');
        $team = $this->createTeam('TEAM-200', 'Roster FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/teams/'.$team->id.'/roster', [
            'player_id' => $playerOne->id,
            'membership_status' => 'active',
        ])->assertCreated();
        $this->postJson('/api/sport/teams/'.$team->id.'/roster', [
            'player_id' => $playerTwo->id,
            'membership_status' => 'active',
        ])->assertCreated();

        $this->putJson('/api/sport/teams/'.$team->id, [
            'name' => 'Roster FC Updated',
            'player_ids' => [$playerTwo->id, $playerThree->id],
        ])->assertOk()
            ->assertJsonPath('data.team.playersCount', 2);

        $this->assertDatabaseHas('sport_teams', [
            'id' => $team->id,
            'name' => 'Roster FC Updated',
            'players_count' => 2,
        ]);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'team_id' => $team->id,
            'player_id' => $playerOne->id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'team_id' => $team->id,
            'player_id' => $playerTwo->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'team_id' => $team->id,
            'player_id' => $playerThree->id,
            'status' => 'active',
        ]);
    }

    public function test_ineligible_selected_players_are_rejected_and_create_rolls_back(): void
    {
        $admin = $this->createUser('adminsport', 'team-admin-3', 'team-admin-3@hfccf.org');
        $otherTeam = $this->createTeam('TEAM-201', 'Other Team');
        $player = $this->createEligiblePlayer('Blocked', 'Player');

        app(SportPlayerMembershipService::class)->activateMembership($player, $otherTeam, $admin, true);

        Sanctum::actingAs($admin);

        $this->postJson('/api/sport/teams', [
            'name' => 'Blocked FC',
            'status' => 'active',
            'player_ids' => [$player->id],
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('sport_teams', [
            'name' => 'Blocked FC',
        ]);
    }

    public function test_coach_cannot_manage_admin_team_routes(): void
    {
        $coach = $this->createUser('coach', 'team-coach-1', 'team-coach-1@hfccf.org');

        Sanctum::actingAs($coach);

        $this->postJson('/api/sport/teams', [
            'name' => 'Coach Blocked FC',
            'status' => 'active',
            'player_ids' => [],
        ])->assertForbidden();
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' '.$id,
            'email' => $email,
            'phone' => '+855 12 700 710',
            'role_code' => $roleCode,
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);
    }

    private function createTeam(string $code, string $name): SportTeam
    {
        return SportTeam::query()->create([
            'team_code' => $code,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createEligiblePlayer(string $firstName, string $lastName): SportPlayer
    {
        return SportPlayer::query()->create([
            'player_code' => 'PLY-'.strtoupper(substr($firstName, 0, 3)).random_int(100, 999),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'approval_status' => 'approved',
            'roster_status' => 'inactive',
            'status' => 'inactive',
        ]);
    }
}
