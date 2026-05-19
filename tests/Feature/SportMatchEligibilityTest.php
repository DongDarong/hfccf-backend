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

class SportMatchEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_coach_can_view_eligibility_and_create_squad_for_assigned_team(): void
    {
        $admin = $this->createUser('superadmin', 'usr-match-admin-1', 'match-admin-1@hfccf.org');
        $coach = $this->createUser('coach', 'usr-match-coach-1', 'match-coach-1@hfccf.org');
        $homeTeam = $this->createTeam('MATCH-T-001', 'Eligible FC');
        $awayTeam = $this->createTeam('MATCH-T-002', 'Opponent FC');
        $player = $this->createApprovedPlayer($homeTeam);
        $this->activatePlayer($player, $homeTeam, $admin);
        $match = $this->createMatch($homeTeam, $awayTeam);

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $homeTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);

        $eligibility = $this->getJson('/api/sport/matches/'.$match->id.'/teams/'.$homeTeam->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $eligibility->json('data.players'));
        $this->assertTrue($eligibility->json('data.players.0.isEligible'));
        $this->assertSame('eligible', $eligibility->json('data.players.0.eligibilityStatus'));

        $save = $this->postJson('/api/sport/matches/'.$match->id.'/teams/'.$homeTeam->id.'/squad', [
            'notes' => 'Match day squad',
            'players' => [
                [
                    'player_id' => $player->id,
                    'role' => 'starter',
                ],
            ],
        ])->assertCreated();

        $save->assertJsonPath('data.squad.status', 'draft');
        $this->assertSame(1, (int) $save->json('data.squad.playersCount'));
        $this->assertSame('starter', $save->json('data.squad.players.0.role'));
        $this->assertTrue((bool) $save->json('data.squad.players.0.isEligible'));
    }

    public function test_ineligible_players_are_blocked_from_selection(): void
    {
        foreach (['pending', 'injured', 'suspended', 'inactive', 'released', 'archived'] as $status) {
            $admin = $this->createUser('superadmin', 'usr-match-admin-'.$status, 'match-admin-'.$status.'@hfccf.org');
            $coach = $this->createUser('coach', 'usr-match-coach-'.$status, 'match-coach-'.$status.'@hfccf.org');
            $homeTeam = $this->createTeam('MATCH-T-'.$status.'-A', 'Blocked '.$status);
            $awayTeam = $this->createTeam('MATCH-T-'.$status.'-B', 'Opponent '.$status);
            $match = $this->createMatch($homeTeam, $awayTeam);

            Sanctum::actingAs($admin);
            $this->postJson('/api/sport/admin/coach-team-assignments', [
                'coach_user_id' => $coach->id,
                'team_id' => $homeTeam->id,
                'status' => 'active',
            ])->assertCreated();

            Sanctum::actingAs($coach);
            $player = $status === 'pending'
                ? $this->makePendingPlayer($homeTeam)
                : $this->makePlayerWithStatus($homeTeam, $status);

            $response = $this->postJson('/api/sport/matches/'.$match->id.'/teams/'.$homeTeam->id.'/squad', [
                'players' => [
                    [
                        'player_id' => $player->id,
                        'role' => 'starter',
                    ],
                ],
            ]);

            $response->assertUnprocessable();
            $this->assertStringContainsString($status, strtolower((string) $response->json('message')));
        }
    }

    public function test_duplicate_players_are_rejected_in_same_squad(): void
    {
        $admin = $this->createUser('adminsport', 'usr-match-admin-3', 'match-admin-3@hfccf.org');
        $coach = $this->createUser('coach', 'usr-match-coach-3', 'match-coach-3@hfccf.org');
        $homeTeam = $this->createTeam('MATCH-T-005', 'Duplicate FC');
        $awayTeam = $this->createTeam('MATCH-T-006', 'Opponent Duplicate');
        $player = $this->createApprovedPlayer($homeTeam);
        $this->activatePlayer($player, $homeTeam, $admin);
        $match = $this->createMatch($homeTeam, $awayTeam);

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $homeTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);

        $this->postJson('/api/sport/matches/'.$match->id.'/teams/'.$homeTeam->id.'/squad', [
            'players' => [
                ['player_id' => $player->id, 'role' => 'starter'],
                ['player_id' => $player->id, 'role' => 'substitute'],
            ],
        ])->assertUnprocessable();
    }

    public function test_coach_cannot_manage_unassigned_team_squad(): void
    {
        $admin = $this->createUser('adminsport', 'usr-match-admin-4', 'match-admin-4@hfccf.org');
        $coach = $this->createUser('coach', 'usr-match-coach-4', 'match-coach-4@hfccf.org');
        $homeTeam = $this->createTeam('MATCH-T-007', 'Assigned FC');
        $awayTeam = $this->createTeam('MATCH-T-008', 'Unassigned FC');
        $match = $this->createMatch($homeTeam, $awayTeam);

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $homeTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);

        $this->getJson('/api/sport/matches/'.$match->id.'/teams/'.$awayTeam->id.'/eligibility')
            ->assertForbidden();
    }

    public function test_admin_can_submit_approve_and_lock_squad(): void
    {
        $admin = $this->createUser('superadmin', 'usr-match-admin-5', 'match-admin-5@hfccf.org');
        $coach = $this->createUser('coach', 'usr-match-coach-5', 'match-coach-5@hfccf.org');
        $homeTeam = $this->createTeam('MATCH-T-009', 'Workflow FC');
        $awayTeam = $this->createTeam('MATCH-T-010', 'Workflow Opponent');
        $player = $this->createApprovedPlayer($homeTeam);
        $this->activatePlayer($player, $homeTeam, $admin);
        $match = $this->createMatch($homeTeam, $awayTeam);

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $homeTeam->id,
            'status' => 'active',
        ])->assertCreated();

        Sanctum::actingAs($coach);
        $squadId = $this->postJson('/api/sport/matches/'.$match->id.'/teams/'.$homeTeam->id.'/squad', [
            'players' => [
                ['player_id' => $player->id, 'role' => 'starter'],
            ],
        ])->json('data.squad.id');

        $this->postJson('/api/sport/match-squads/'.$squadId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.squad.status', 'submitted');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/match-squads/'.$squadId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.squad.status', 'approved');

        $this->postJson('/api/sport/match-squads/'.$squadId.'/lock')
            ->assertOk()
            ->assertJsonPath('data.squad.status', 'locked');

        $this->patchJson('/api/sport/match-squads/'.$squadId, [
            'notes' => 'Should fail after lock.',
            'players' => [
                ['player_id' => $player->id, 'role' => 'starter'],
            ],
        ])->assertUnprocessable();
    }

    public function test_snapshot_fields_persist_after_player_update(): void
    {
        $admin = $this->createUser('superadmin', 'usr-match-admin-6', 'match-admin-6@hfccf.org');
        $homeTeam = $this->createTeam('MATCH-T-011', 'Snapshot FC');
        $awayTeam = $this->createTeam('MATCH-T-012', 'Opponent Snapshot');
        $player = $this->createApprovedPlayer($homeTeam);
        $this->activatePlayer($player, $homeTeam, $admin);
        $match = $this->createMatch($homeTeam, $awayTeam);

        Sanctum::actingAs($admin);
        $squadResponse = $this->postJson('/api/sport/matches/'.$match->id.'/teams/'.$homeTeam->id.'/squad', [
            'players' => [
                ['player_id' => $player->id, 'role' => 'starter'],
            ],
        ])->assertCreated();

        $originalName = $player->first_name.' '.$player->last_name;
        $originalJersey = $player->jersey_number;
        $squadPlayer = $squadResponse->json('data.squad.players.0');
        $this->assertSame($originalName, $squadPlayer['playerNameSnapshot']);
        $this->assertSame($originalJersey, $squadPlayer['jerseyNumberSnapshot']);

        $player->forceFill([
            'first_name' => 'Updated',
            'last_name' => 'Player',
            'jersey_number' => 99,
        ])->save();

        $show = $this->getJson('/api/sport/matches/'.$match->id.'/teams/'.$homeTeam->id.'/squad')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(99, (int) $player->fresh()->jersey_number);
        $this->assertSame($originalName, $show->json('data.squad.players.0.playerNameSnapshot'));
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' '.$id,
            'email' => $email,
            'phone' => '+855 12 633 633',
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

    private function createMatch(SportTeam $homeTeam, SportTeam $awayTeam): SportMatch
    {
        return SportMatch::query()->create([
            'match_code' => 'MATCH-'.strtoupper((string) random_int(100000, 999999)),
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'scheduled',
            'approval_status' => 'approved',
            'scheduled_at' => now()->addDay(),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    private function createApprovedPlayer(SportTeam $team): SportPlayer
    {
        return SportPlayer::query()->create([
            'player_code' => 'PLY-'.strtoupper($team->team_code).'-'.random_int(100, 999),
            'first_name' => 'Match',
            'last_name' => 'Player',
            'jersey_number' => 10,
            'position' => 'Forward',
            'approval_status' => 'approved',
            'roster_status' => 'active',
            'status' => 'active',
        ]);
    }

    private function activatePlayer(SportPlayer $player, SportTeam $team, User $actor): void
    {
        app(SportPlayerMembershipService::class)->activateMembership($player, $team, $actor, true);
    }

    private function makePendingPlayer(SportTeam $team): SportPlayer
    {
        $player = SportPlayer::query()->create([
            'player_code' => 'PLY-PENDING-'.random_int(100, 999),
            'first_name' => 'Pending',
            'last_name' => 'Player',
            'jersey_number' => 11,
            'position' => 'Midfielder',
            'approval_status' => 'pending',
            'roster_status' => 'inactive',
            'status' => 'inactive',
            'team_id' => $team->id,
        ]);

        app(SportPlayerMembershipService::class)->createPendingMembership($player, $team, null);

        return $player;
    }

    private function makePlayerWithStatus(SportTeam $team, string $status): SportPlayer
    {
        $player = $this->createApprovedPlayer($team);
        $this->activatePlayer($player, $team, $this->createUser('adminsport', 'usr-match-admin-temp-'.$status, 'temp-'.$status.'@hfccf.org'));

        $player->forceFill([
            'roster_status' => $status,
            'status' => $status,
            'team_id' => $team->id,
        ])->save();

        return $player->fresh();
    }
}
