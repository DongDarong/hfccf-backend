<?php

namespace Tests\Feature;

use App\Models\SportAttendanceRecord;
use App\Models\SportPlayer;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\SportPlayerMembershipService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_coach_attendance_is_scoped_to_assigned_teams_and_active_memberships(): void
    {
        $admin = $this->createUser('superadmin', 'att-admin-1', 'att-admin-1@hfccf.org');
        $coach = $this->createUser('coach', 'att-coach-1', 'att-coach-1@hfccf.org');
        $assignedTeam = $this->createTeam('ATT-TEAM-1', 'Assigned FC');
        $foreignTeam = $this->createTeam('ATT-TEAM-2', 'Foreign FC');
        $assignedPlayer = $this->createApprovedPlayer('Assigned', 'Player');
        $foreignPlayer = $this->createApprovedPlayer('Foreign', 'Player');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $assignedTeam->id,
            'status' => 'active',
        ])->assertCreated();

        app(SportPlayerMembershipService::class)->activateMembership($assignedPlayer, $assignedTeam, $admin, true);
        app(SportPlayerMembershipService::class)->activateMembership($foreignPlayer, $foreignTeam, $admin, true);

        $foreignSeed = $this->postJson('/api/sport/attendance', [
            'team_id' => $foreignTeam->id,
            'player_id' => $foreignPlayer->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
            'note' => 'Admin seed row',
        ])->assertCreated();
        $foreignAttendanceId = $foreignSeed->json('data.attendance.id');

        Sanctum::actingAs($coach);

        $index = $this->getJson('/api/sport/attendance?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $index->json('data.items');
        $this->assertCount(0, $items);

        $this->postJson('/api/sport/attendance', [
            'team_id' => $foreignTeam->id,
            'player_id' => $foreignPlayer->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
        ])->assertForbidden();

        $this->postJson('/api/sport/attendance', [
            'team_id' => $assignedTeam->id,
            'player_id' => $foreignPlayer->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
        ])->assertUnprocessable();

        $this->putJson("/api/sport/attendance/{$foreignAttendanceId}", [
            'status' => 'absent',
            'note' => 'Unauthorized update attempt',
        ])->assertForbidden();

        $create = $this->postJson('/api/sport/attendance', [
            'team_id' => $assignedTeam->id,
            'player_id' => $assignedPlayer->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
            'note' => 'First save',
        ])->assertCreated();

        $attendanceId = $create->json('data.attendance.id');
        $this->assertNotEmpty($attendanceId);

        $this->assertSame(1, SportAttendanceRecord::query()
            ->where('subject_key', 'player:'.$assignedPlayer->id)
            ->whereDate('attendance_date', '2026-07-01')
            ->count());

        $this->postJson('/api/sport/attendance', [
            'team_id' => $assignedTeam->id,
            'player_id' => $assignedPlayer->id,
            'attendance_date' => '2026-07-01',
            'status' => 'late',
            'note' => 'Updated save',
        ])->assertCreated()
            ->assertJsonPath('data.attendance.id', $attendanceId)
            ->assertJsonPath('data.attendance.status', 'late');

        $this->putJson("/api/sport/attendance/{$attendanceId}", [
            'team_id' => $foreignTeam->id,
            'status' => 'absent',
            'note' => 'Team spoof attempt',
        ])->assertForbidden();

        $this->putJson("/api/sport/attendance/{$attendanceId}", [
            'player_id' => $foreignPlayer->id,
            'status' => 'excused',
            'note' => 'Player spoof attempt',
        ])->assertUnprocessable();

        $this->assertSame(1, SportAttendanceRecord::query()
            ->where('subject_key', 'player:'.$assignedPlayer->id)
            ->whereDate('attendance_date', '2026-07-01')
            ->count());

        $this->assertDatabaseHas('sport_attendance_records', [
            'id' => $attendanceId,
            'team_id' => $assignedTeam->id,
            'player_id' => $assignedPlayer->id,
            'attendance_date' => '2026-07-01 00:00:00',
            'status' => 'late',
        ]);
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' '.$id,
            'email' => $email,
            'phone' => '+855 12 700 720',
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

    private function createApprovedPlayer(string $firstName, string $lastName): SportPlayer
    {
        return SportPlayer::query()->create([
            'player_code' => 'PLY-'.strtoupper(substr($firstName, 0, 3)).random_int(100, 999),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'approval_status' => 'approved',
            'roster_status' => 'active',
            'status' => 'active',
        ]);
    }
}
