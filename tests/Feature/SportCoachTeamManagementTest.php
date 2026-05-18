<?php

namespace Tests\Feature;

use App\Models\SportTeam;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportCoachTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_coach_only_sees_assigned_teams(): void
    {
        $admin = $this->createUser('superadmin', 'usr_1000', 'admin1000@hfccf.org');
        $coach = $this->createUser('coach', 'usr_1001', 'coach1001@hfccf.org');
        $otherCoach = $this->createUser('coach', 'usr_1002', 'coach1002@hfccf.org');
        $assignedTeam = $this->createTeam('TEAM-1000', 'Assigned FC');
        $unassignedTeam = $this->createTeam('TEAM-1001', 'Unassigned FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $assignedTeam->id,
            'status' => 'active',
        ])->assertCreated();

        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $otherCoach->id,
            'team_id' => $unassignedTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);

        $response = $this->getJson('/api/sport/coach/teams')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame($assignedTeam->id, $items[0]['id']);
        $this->assertSame('Assigned FC', $items[0]['name']);
    }

    public function test_coach_cannot_manage_unassigned_team(): void
    {
        $coach = $this->createUser('coach', 'usr_1003', 'coach1003@hfccf.org');
        $team = $this->createTeam('TEAM-1003', 'Blocked FC');

        Sanctum::actingAs($coach);

        $this->getJson('/api/sport/coach/teams/'.$team->id)
            ->assertForbidden();
    }

    public function test_coach_created_player_starts_pending_until_admin_approval(): void
    {
        $admin = $this->createUser('adminsport', 'usr_1004', 'admin1004@hfccf.org');
        $coach = $this->createUser('coach', 'usr_1005', 'coach1005@hfccf.org');
        $team = $this->createTeam('TEAM-1004', 'Pending FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);

        $response = $this->postJson('/api/sport/coach/teams/'.$team->id.'/players', [
            'name' => 'Sok Pending',
            'jersey_number' => 9,
            'primary_position' => 'Forward',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.player.approvalStatus', 'pending')
            ->assertJsonPath('data.player.status', 'pending')
            ->assertJsonPath('data.player.teamId', $team->id);

        $playerId = $response->json('data.player.id');

        $this->assertDatabaseHas('sport_players', [
            'id' => $playerId,
            'approval_status' => 'pending',
            'created_by_user_id' => $coach->id,
            'team_id' => $team->id,
        ]);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'player_id' => $playerId,
            'team_id' => $team->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/sport/admin/players/'.$playerId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.player.approvalStatus', 'approved')
            ->assertJsonPath('data.player.status', 'active');

        $this->assertDatabaseHas('sport_players', [
            'id' => $playerId,
            'approval_status' => 'approved',
            'approved_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('sport_player_team_memberships', [
            'player_id' => $playerId,
            'team_id' => $team->id,
            'status' => 'active',
        ]);
    }

    public function test_pending_player_listing_and_rejection_work(): void
    {
        $admin = $this->createUser('adminsport', 'usr_1006', 'admin1006@hfccf.org');
        $coach = $this->createUser('coach', 'usr_1007', 'coach1007@hfccf.org');
        $team = $this->createTeam('TEAM-1005', 'Review FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);
        $playerId = $this->postJson('/api/sport/coach/teams/'.$team->id.'/players', [
            'name' => 'Sok Review',
        ])->json('data.player.id');

        Sanctum::actingAs($admin);

        $pending = $this->getJson('/api/sport/admin/pending-players')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $pending->json('data.items'));

        $this->postJson('/api/sport/admin/players/'.$playerId.'/reject', [
            'rejection_reason' => 'Duplicate registration.',
        ])->assertOk()
            ->assertJsonPath('data.player.approvalStatus', 'rejected');

        $this->assertDatabaseHas('sport_players', [
            'id' => $playerId,
            'approval_status' => 'rejected',
            'rejection_reason' => 'Duplicate registration.',
        ]);
    }

    public function test_coach_can_request_match_and_admin_can_approve_or_reject(): void
    {
        $admin = $this->createUser('adminsport', 'usr_1008', 'admin1008@hfccf.org');
        $coach = $this->createUser('coach', 'usr_1009', 'coach1009@hfccf.org');
        $opponent = $this->createTeam('TEAM-1006', 'Opponent FC');
        $coachTeam = $this->createTeam('TEAM-1007', 'Coach Side FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $coachTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);

        $matchResponse = $this->postJson('/api/sport/coach/matches', [
            'team_id' => $coachTeam->id,
            'opponent_team_id' => $opponent->id,
            'match_type' => 'training',
            'scheduled_at' => '2026-05-20 15:00:00',
            'venue' => 'Main Ground',
        ]);

        $matchResponse
            ->assertCreated()
            ->assertJsonPath('data.match.approvalStatus', 'pending')
            ->assertJsonPath('data.match.status', 'draft')
            ->assertJsonPath('data.match.homeTeamId', $coachTeam->id)
            ->assertJsonPath('data.match.awayTeamId', $opponent->id);

        $matchId = $matchResponse->json('data.match.id');

        $this->assertDatabaseHas('sport_matches', [
            'id' => $matchId,
            'approval_status' => 'pending',
            'requested_by_role' => 'coach',
            'created_by_user_id' => $coach->id,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/sport/admin/pending-matches')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/sport/admin/matches/'.$matchId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.match.approvalStatus', 'approved')
            ->assertJsonPath('data.match.status', 'scheduled');

        $rejected = $this->postJson('/api/sport/coach/matches', [
            'team_id' => $coachTeam->id,
            'opponent_team_id' => $opponent->id,
            'match_type' => 'friendly',
            'scheduled_at' => '2026-05-21 15:00:00',
            'venue' => 'Main Ground',
        ]);

        $rejectMatchId = $rejected->json('data.match.id');

        $this->postJson('/api/sport/admin/matches/'.$rejectMatchId.'/reject', [
            'rejection_reason' => 'Not available.',
        ])->assertOk()
            ->assertJsonPath('data.match.approvalStatus', 'rejected')
            ->assertJsonPath('data.match.status', 'cancelled');
    }

    public function test_coach_cannot_create_match_for_unassigned_team(): void
    {
        $coach = $this->createUser('coach', 'usr_1010', 'coach1010@hfccf.org');
        $team = $this->createTeam('TEAM-1010', 'Blocked Team');
        $opponent = $this->createTeam('TEAM-1011', 'Opponent Team');

        Sanctum::actingAs($coach);

        $this->postJson('/api/sport/coach/matches', [
            'team_id' => $team->id,
            'opponent_team_id' => $opponent->id,
            'match_type' => 'friendly',
            'scheduled_at' => '2026-05-20 15:00:00',
        ])->assertForbidden();
    }

    public function test_guest_users_are_blocked_from_coach_routes(): void
    {
        $this->getJson('/api/sport/coach/teams')
            ->assertUnauthorized();
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        $uniqueSuffix = substr($id, -4);

        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            // Keep usernames unique because the base seeder already creates canonical role accounts.
            'username' => ucfirst($roleCode).' User '.$uniqueSuffix,
            'email' => $email,
            'phone' => '+855 12 700 700',
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
}
