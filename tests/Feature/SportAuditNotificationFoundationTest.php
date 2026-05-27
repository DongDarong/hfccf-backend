<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\SportTeam;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportAuditNotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_player_approval_creates_audit_log_and_notifies_coach(): void
    {
        $coach = $this->actingAsRole('coach', 'usr_audit_001', 'coach.audit001@hfccf.org');
        $team = $this->createTeam('TEAM-AUD-1', 'Audit Team One', $coach->id);
        Sanctum::actingAs($coach);

        $request = $this->postJson('/api/sport/coach/teams/'.$team->id.'/players', [
            'name' => 'Audit Player',
            'first_name' => 'Audit',
            'last_name' => 'Player',
            'team_id' => $team->id,
            'primary_position' => 'Forward',
            'jersey_number' => 9,
        ])->assertCreated();

        $playerId = $request->json('data.player.id');

        $this->actingAsRole('adminsport', 'usr_audit_002', 'sport.audit002@hfccf.org');
        $this->postJson('/api/sport/admin/players/'.$playerId.'/approve')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'domain' => 'sport',
            'action' => 'player_approved',
            'entity_type' => 'sport_player',
            'entity_id' => (string) $playerId,
        ]);

        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $coach->id,
        ]);
    }

    public function test_coach_assignment_creates_audit_log_and_notification(): void
    {
        $coach = $this->actingAsRole('coach', 'usr_audit_003', 'coach.audit003@hfccf.org');
        $team = $this->createTeam('TEAM-AUD-2', 'Audit Team Two');

        $this->actingAsRole('adminsport', 'usr_audit_004', 'sport.audit004@hfccf.org');
        $response = $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
        ])->assertCreated();

        $assignmentId = $response->json('data.assignment.id');

        $this->assertDatabaseHas('audit_logs', [
            'domain' => 'sport',
            'action' => 'coach_assignment_created',
            'entity_type' => 'coach_team_assignment',
            'entity_id' => (string) $assignmentId,
        ]);

        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $coach->id,
        ]);
    }

    public function test_users_can_read_own_notifications_and_mark_them_read(): void
    {
        $coachA = $this->actingAsRole('coach', 'usr_audit_005', 'coach.audit005@hfccf.org');
        $coachB = $this->createUser('coach', 'usr_audit_006', 'coach.audit006@hfccf.org');

        $notification = Notification::query()->create([
            'type' => 'info',
            'title' => 'System Notice',
            'message' => 'Your roster has changed.',
            'module' => 'sport',
            'created_by' => $coachA->id,
        ]);

        NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'user_id' => $coachA->id,
            'created_at' => now(),
        ]);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $notification->id);

        Sanctum::actingAs($coachB);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        Sanctum::actingAs($coachA);

        $this->patchJson('/api/notifications/'.$notification->id.'/read')
            ->assertOk();

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $coachA->id,
        ]);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk();
    }

    public function test_admin_can_read_audit_logs_and_coach_is_blocked(): void
    {
        AuditLog::query()->create([
            'actor_user_id' => null,
            'domain' => 'sport',
            'action' => 'player_approved',
            'entity_type' => 'sport_player',
            'entity_id' => '1',
            'entity_label' => 'Audit Player',
            'new_values' => ['approval_status' => 'approved'],
            'created_at' => now(),
        ]);

        $coach = $this->actingAsRole('coach', 'usr_audit_007', 'coach.audit007@hfccf.org');

        $this->getJson('/api/audit-logs')
            ->assertForbidden();

        $admin = $this->actingAsRole('adminsport', 'usr_audit_008', 'sport.audit008@hfccf.org');

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.items.0.action', 'player_approved');

        $this->assertNotSame($coach->id, $admin->id);
    }

    private function actingAsRole(string $roleCode, string $id, string $email): User
    {
        $user = $this->createUser($roleCode, $id, $email);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            // Reuse the stable test id so each fixture stays unique across the
            // suite; usernames are unique in this backend and duplicate labels
            // would otherwise mask the actual audit/notification behavior.
            'username' => $id,
            'email' => $email,
            'phone' => '+855 12 620 620',
            'role_code' => $roleCode,
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);
    }

    private function createTeam(string $code, string $name, ?string $coachUserId = null): SportTeam
    {
        return SportTeam::query()->create([
            'team_code' => $code,
            'name' => $name,
            'coach_user_id' => $coachUserId,
            'status' => 'active',
        ]);
    }
}
