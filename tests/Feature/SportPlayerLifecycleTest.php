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

class SportPlayerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_coach_can_list_only_assigned_team_roster(): void
    {
        $admin = $this->createUser('superadmin', 'usr-lifecycle-admin-1', 'lifecycle-admin-1@hfccf.org');
        $coach = $this->createUser('coach', 'usr-lifecycle-coach-1', 'lifecycle-coach-1@hfccf.org');
        $assignedTeam = $this->createTeam('LIFE-T-001', 'Lifecycle Assigned');
        $unassignedTeam = $this->createTeam('LIFE-T-002', 'Lifecycle Blocked');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $assignedTeam->id,
            'status' => 'active',
        ])->assertCreated();

        $player = $this->createApprovedPlayer($assignedTeam);
        app(SportPlayerMembershipService::class)->activateMembership($player, $assignedTeam, $admin, true);

        Sanctum::actingAs($coach);

        $response = $this->getJson('/api/sport/teams/'.$assignedTeam->id.'/roster')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data.players'));

        $this->getJson('/api/sport/teams/'.$unassignedTeam->id.'/roster')
            ->assertForbidden();
    }

    public function test_coach_created_player_remains_pending_and_inactive_until_approved(): void
    {
        $admin = $this->createUser('adminsport', 'usr-lifecycle-admin-2', 'lifecycle-admin-2@hfccf.org');
        $coach = $this->createUser('coach', 'usr-lifecycle-coach-2', 'lifecycle-coach-2@hfccf.org');
        $team = $this->createTeam('LIFE-T-003', 'Pending Team');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);

        $response = $this->postJson('/api/sport/coach/teams/'.$team->id.'/players', [
            'name' => 'Lifecycle Pending',
            'jersey_number' => 17,
            'primary_position' => 'Forward',
        ])->assertCreated();

        $playerId = $response->json('data.player.id');

        $this->assertDatabaseHas('sport_players', [
            'id' => $playerId,
            'approval_status' => 'pending',
            'roster_status' => 'inactive',
            'created_by_user_id' => $coach->id,
        ]);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'player_id' => $playerId,
            'team_id' => $team->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_approval_activates_player_and_membership(): void
    {
        $admin = $this->createUser('adminsport', 'usr-lifecycle-admin-3', 'lifecycle-admin-3@hfccf.org');
        $coach = $this->createUser('coach', 'usr-lifecycle-coach-3', 'lifecycle-coach-3@hfccf.org');
        $team = $this->createTeam('LIFE-T-004', 'Approval Team');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);
        $playerId = $this->postJson('/api/sport/coach/teams/'.$team->id.'/players', [
            'name' => 'Lifecycle Approve',
            'jersey_number' => 11,
            'primary_position' => 'Midfielder',
        ])->json('data.player.id');

        Sanctum::actingAs($admin);
        $approve = $this->postJson('/api/sport/admin/players/'.$playerId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.player.approvalStatus', 'approved')
            ->assertJsonPath('data.player.rosterStatus', 'active');

        $this->assertDatabaseHas('sport_players', [
            'id' => $playerId,
            'approval_status' => 'approved',
            'roster_status' => 'active',
        ]);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'player_id' => $playerId,
            'team_id' => $team->id,
            'status' => 'active',
        ]);
    }

    public function test_player_cannot_join_two_active_teams(): void
    {
        $admin = $this->createUser('superadmin', 'usr-lifecycle-admin-4', 'lifecycle-admin-4@hfccf.org');
        $teamA = $this->createTeam('LIFE-T-005', 'Team A');
        $teamB = $this->createTeam('LIFE-T-006', 'Team B');
        $player = $this->createApprovedPlayer($teamA);

        app(SportPlayerMembershipService::class)->activateMembership($player, $teamA, $admin, true);

        Sanctum::actingAs($admin);

        $this->postJson('/api/sport/teams/'.$teamB->id.'/roster', [
            'player_id' => $player->id,
            'membership_status' => 'active',
        ])->assertUnprocessable();
    }

    public function test_suspension_and_release_update_lifecycle_and_memberships(): void
    {
        $admin = $this->createUser('superadmin', 'usr-lifecycle-admin-5', 'lifecycle-admin-5@hfccf.org');
        $team = $this->createTeam('LIFE-T-007', 'Discipline Team');
        $player = $this->createApprovedPlayer($team);
        app(SportPlayerMembershipService::class)->activateMembership($player, $team, $admin, true);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/sport/players/'.$player->id.'/suspension', [
            'notes' => 'Late return.',
            'suspension_until' => '2026-06-01',
        ])->assertOk()
            ->assertJsonPath('data.player.rosterStatus', 'suspended');

        $this->patchJson('/api/sport/players/'.$player->id.'/release', [
            'notes' => 'Released from roster.',
        ])->assertOk()
            ->assertJsonPath('data.player.rosterStatus', 'released');

        $this->assertDatabaseHas('sport_players', [
            'id' => $player->id,
            'roster_status' => 'released',
            'team_id' => null,
        ]);

        $history = $this->getJson('/api/sport/players/'.$player->id.'/history')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($history->json('data.memberships'));
    }

    public function test_archived_player_is_hidden_from_active_roster(): void
    {
        $admin = $this->createUser('superadmin', 'usr-lifecycle-admin-6', 'lifecycle-admin-6@hfccf.org');
        $team = $this->createTeam('LIFE-T-008', 'Archive Team');
        $player = $this->createApprovedPlayer($team);
        app(SportPlayerMembershipService::class)->activateMembership($player, $team, $admin, true);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/sport/players/'.$player->id.'/archive', [
            'notes' => 'Archived for history.',
        ])->assertOk();

        $response = $this->getJson('/api/sport/teams/'.$team->id.'/roster')
            ->assertOk();

        $this->assertCount(0, $response->json('data.players'));
    }

    public function test_guest_users_are_blocked_from_roster_routes(): void
    {
        $team = $this->createTeam('LIFE-T-009', 'Guest Blocked');

        $this->getJson('/api/sport/teams/'.$team->id.'/roster')
            ->assertUnauthorized();
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' '.$id,
            'email' => $email,
            'phone' => '+855 12 700 701',
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

    private function createApprovedPlayer(SportTeam $team): SportPlayer
    {
        return SportPlayer::query()->create([
            'player_code' => 'PLY-'.strtoupper($team->team_code).'-'.random_int(100, 999),
            'first_name' => 'Lifecycle',
            'last_name' => 'Player',
            'jersey_number' => 9,
            'position' => 'Forward',
            'team_id' => null,
            'approval_status' => 'approved',
            'roster_status' => 'inactive',
            'status' => 'inactive',
        ]);
    }
}
