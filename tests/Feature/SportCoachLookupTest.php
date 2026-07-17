<?php

namespace Tests\Feature;

use App\Models\SportMatch;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\SportPlayerMembershipService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportCoachLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_coach_sees_only_own_requests(): void
    {
        $admin = $this->createUser('adminsport', 'lookup-admin-0', 'lookup-admin-0@hfccf.org');
        $coach = $this->createUser('coach', 'lookup-coach-1', 'lookup-coach-1@hfccf.org');
        $otherCoach = $this->createUser('coach', 'lookup-coach-2', 'lookup-coach-2@hfccf.org');
        $team = $this->createTeam('LOOK-T-1', 'Lookup Team');
        $opponent = $this->createTeam('LOOK-T-2', 'Opponent Team');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);
        $player = $this->postJson('/api/sport/coach/teams/'.$team->id.'/players', [
            'name' => 'Lookup Player',
        ])->assertCreated()->json('data.player');

        $match = $this->postJson('/api/sport/coach/matches', [
            'team_id' => $team->id,
            'opponent_team_id' => $opponent->id,
            'match_type' => 'friendly',
        ])->assertCreated()->json('data.match');

        Sanctum::actingAs($otherCoach);
        $this->postJson('/api/sport/coach/teams/'.$opponent->id.'/players', [
            'name' => 'Other Coach Player',
        ])->assertForbidden();

        Sanctum::actingAs($coach);
        $response = $this->getJson('/api/sport/coach/requests')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data.playerRequests'));
        $this->assertCount(1, $response->json('data.matchRequests'));
        $this->assertSame($player['id'], $response->json('data.playerRequests.0.id'));
        $this->assertSame($match['id'], $response->json('data.matchRequests.0.id'));

        Sanctum::actingAs($otherCoach);
        $this->getJson('/api/sport/coach/requests')->assertOk()->assertJsonCount(0, 'data.playerRequests');
    }

    public function test_admin_approval_queue_remains_admin_only(): void
    {
        $coach = $this->createUser('coach', 'lookup-coach-3', 'lookup-coach-3@hfccf.org');
        Sanctum::actingAs($coach);

        $this->getJson('/api/sport/admin/pending-players')
            ->assertForbidden();

        $this->getJson('/api/sport/admin/pending-matches')
            ->assertForbidden();
    }

    public function test_coach_can_load_opponent_teams_but_not_inactive_or_own_teams(): void
    {
        $admin = $this->createUser('adminsport', 'lookup-admin-1', 'lookup-admin-1@hfccf.org');
        $coach = $this->createUser('coach', 'lookup-coach-4', 'lookup-coach-4@hfccf.org');
        $assignedTeam = $this->createTeam('LOOK-T-3', 'Assigned Team');
        $opponentTeam = $this->createTeam('LOOK-T-4', 'Opponent Team');
        $inactiveTeam = $this->createTeam('LOOK-T-5', 'Inactive Team', 'inactive');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $assignedTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);
        $response = $this->getJson('/api/sport/coach/opponent-teams')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');
        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($opponentTeam->id, $ids);
        $this->assertNotContains($assignedTeam->id, $ids);
        $this->assertNotContains($inactiveTeam->id, $ids);
    }

    public function test_coach_can_load_roster_candidates_for_assigned_team_only(): void
    {
        $admin = $this->createUser('adminsport', 'lookup-admin-2', 'lookup-admin-2@hfccf.org');
        $coach = $this->createUser('coach', 'lookup-coach-5', 'lookup-coach-5@hfccf.org');
        $team = $this->createTeam('LOOK-T-6', 'Roster Team');
        $blockedTeam = $this->createTeam('LOOK-T-7', 'Blocked Team');
        $eligiblePlayer = $this->createApprovedPlayer('Approved Candidate', 'Candidate');
        $blockedPlayer = $this->createArchivedPlayer('Archived Candidate', 'Candidate');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);
        $response = $this->getJson('/api/sport/coach/teams/'.$team->id.'/roster-candidates')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');
        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($eligiblePlayer->id, $ids);
        $this->assertNotContains($blockedPlayer->id, $ids);

        $this->getJson('/api/sport/coach/teams/'.$blockedTeam->id.'/roster-candidates')
            ->assertForbidden();
    }

    public function test_guest_users_are_blocked_from_lookup_routes(): void
    {
        $this->getJson('/api/sport/coach/requests')->assertUnauthorized();
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' '.$id,
            'email' => $email,
            'phone' => '+855 12 711 711',
            'role_code' => $roleCode,
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);
    }

    private function createTeam(string $code, string $name, string $status = 'active'): SportTeam
    {
        return SportTeam::query()->create([
            'team_code' => $code,
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function createApprovedPlayer(string $firstName, string $lastName): SportPlayer
    {
        $player = SportPlayer::query()->create([
            'player_code' => 'PLY-'.strtoupper(substr($firstName, 0, 3)).random_int(100, 999),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'approval_status' => 'approved',
            'roster_status' => 'inactive',
            'status' => 'inactive',
        ]);

        return $player->refresh();
    }

    private function createArchivedPlayer(string $firstName, string $lastName): SportPlayer
    {
        $player = SportPlayer::query()->create([
            'player_code' => 'PLY-'.strtoupper(substr($firstName, 0, 3)).random_int(100, 999),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'approval_status' => 'approved',
            'roster_status' => 'archived',
            'status' => 'archived',
        ]);

        return $player->refresh();
    }
}
