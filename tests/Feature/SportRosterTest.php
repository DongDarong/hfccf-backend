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

class SportRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_load_roster_candidates_for_add_mode_and_search(): void
    {
        $admin = $this->createUser('adminsport', 'roster-admin-1', 'roster-admin-1@hfccf.org');
        $selected = $this->createPlayer('Eligible', 'Player');
        $blockedArchived = $this->createPlayer('Archived', 'Player', 'approved', 'archived');
        $blockedReleased = $this->createPlayer('Released', 'Player', 'approved', 'released');
        $otherTeam = $this->createTeam('ROSTER-T-1', 'Other Team');
        $blockedActive = $this->createPlayer('Active', 'Elsewhere');

        app(SportPlayerMembershipService::class)->activateMembership($blockedActive, $otherTeam, $admin, true);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/sport/admin/teams/roster-candidates?search=Eligible')
            ->assertOk()
            ->assertJsonPath('success', true);

        $response->assertJsonPath('data.team', null);
        $response->assertJsonPath('data.pagination.total', 1);

        $items = $response->json('data.items');
        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($selected->id, $ids);
        $this->assertNotContains($blockedArchived->id, $ids);
        $this->assertNotContains($blockedReleased->id, $ids);
        $this->assertNotContains($blockedActive->id, $ids);

        Sanctum::actingAs($this->createUser('coach', 'roster-coach-1', 'roster-coach-1@hfccf.org'));
        $this->getJson('/api/sport/admin/teams/roster-candidates')->assertForbidden();
    }

    public function test_admin_can_load_team_specific_roster_candidates_for_edit_mode(): void
    {
        $admin = $this->createUser('superadmin', 'roster-admin-2', 'roster-admin-2@hfccf.org');
        $team = $this->createTeam('ROSTER-T-2', 'Edit Team');
        $existing = $this->createPlayer('Current', 'Member');
        $newCandidate = $this->createPlayer('Fresh', 'Candidate');
        $otherTeam = $this->createTeam('ROSTER-T-3', 'Other Team');
        $blocked = $this->createPlayer('Blocked', 'Candidate');

        app(SportPlayerMembershipService::class)->activateMembership($existing, $team, $admin, true);
        app(SportPlayerMembershipService::class)->activateMembership($blocked, $otherTeam, $admin, true);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/sport/admin/teams/'.$team->id.'/roster-candidates')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonPath('data.team.playersCount', 1);

        $items = $response->json('data.items');
        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($existing->id, $ids);
        $this->assertContains($newCandidate->id, $ids);
        $this->assertNotContains($blocked->id, $ids);
    }

    public function test_coach_candidate_lookup_remains_scoped_to_assigned_team(): void
    {
        $admin = $this->createUser('adminsport', 'roster-admin-3', 'roster-admin-3@hfccf.org');
        $coach = $this->createUser('coach', 'roster-coach-2', 'roster-coach-2@hfccf.org');
        $assignedTeam = $this->createTeam('ROSTER-T-4', 'Assigned Team');
        $blockedTeam = $this->createTeam('ROSTER-T-5', 'Blocked Team');
        $eligible = $this->createPlayer('Coach', 'Eligible');
        $blocked = $this->createPlayer('Coach', 'Blocked', 'approved', 'archived');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $assignedTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);
        $response = $this->getJson('/api/sport/coach/teams/'.$assignedTeam->id.'/roster-candidates')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');
        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($eligible->id, $ids);
        $this->assertNotContains($blocked->id, $ids);

        $this->getJson('/api/sport/coach/teams/'.$blockedTeam->id.'/roster-candidates')
            ->assertForbidden();
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

    private function createPlayer(string $firstName, string $lastName, string $approvalStatus = 'approved', string $rosterStatus = 'inactive'): SportPlayer
    {
        return SportPlayer::query()->create([
            'player_code' => 'PLY-'.strtoupper(substr($firstName, 0, 3)).random_int(100, 999),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'approval_status' => $approvalStatus,
            'roster_status' => $rosterStatus,
            'status' => $rosterStatus,
        ]);
    }
}
