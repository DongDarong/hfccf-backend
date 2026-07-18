<?php

namespace Tests\Feature;

use App\Models\SportTeam;
use App\Models\SportTrainingSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportTrainingScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_and_list_training_sessions(): void
    {
        $admin = $this->createUserWithRole('adminsport');
        $team = $this->createTeam('TRN-TEAM-1', 'Lions FC');
        Sanctum::actingAs($admin);

        $this->postJson('/api/sport/training-sessions', $this->payload($team))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Technical training');

        $this->getJson('/api/sport/training-sessions')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.teamId', $team->id);
    }

    public function test_admin_can_update_and_delete_training_sessions(): void
    {
        $admin = $this->createUserWithRole('adminsport');
        $team = $this->createTeam('TRN-TEAM-2', 'Tigers FC');
        Sanctum::actingAs($admin);
        $session = SportTrainingSession::query()->create($this->payload($team) + [
            'session_code' => 'TRN-SESSION-2',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->putJson('/api/sport/training-sessions/'.$session->id, [
            'title' => 'Tactical training',
            'training_type' => 'tactical',
            'starts_at' => '2026-08-01 09:00:00',
            'ends_at' => '2026-08-01 11:00:00',
            'intensity' => 'high',
            'status' => 'scheduled',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Tactical training')
            ->assertJsonPath('data.trainingType', 'tactical');

        $this->deleteJson('/api/sport/training-sessions/'.$session->id)->assertOk();
        $this->assertSoftDeleted('sport_training_sessions', ['id' => $session->id]);
    }

    public function test_coach_can_view_assigned_team_sessions_only(): void
    {
        $admin = $this->createUserWithRole('adminsport');
        $coach = $this->createUserWithRole('coach');
        $assignedTeam = $this->createTeam('TRN-TEAM-3', 'Assigned FC', $coach->id);
        $otherTeam = $this->createTeam('TRN-TEAM-4', 'Other FC');
        $this->createSession($assignedTeam, $admin);
        $otherSession = $this->createSession($otherTeam, $admin);

        Sanctum::actingAs($coach);
        $this->getJson('/api/sport/training-sessions')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonMissing(['id' => $otherSession->id]);

        $this->getJson('/api/sport/training-sessions/'.$otherSession->id)->assertNotFound();
    }

    public function test_coach_is_read_only_and_other_roles_are_forbidden(): void
    {
        $coach = $this->createUserWithRole('coach');
        $team = $this->createTeam('TRN-TEAM-5', 'Coach FC', $coach->id);
        Sanctum::actingAs($coach);

        $this->postJson('/api/sport/training-sessions', $this->payload($team))->assertForbidden();

        $otherRole = $this->createUserWithRole('adminenglish');
        Sanctum::actingAs($otherRole);
        $this->getJson('/api/sport/training-sessions')->assertForbidden();
    }

    public function test_validation_rejects_invalid_schedule_values(): void
    {
        Sanctum::actingAs($this->createUserWithRole('adminsport'));
        $team = $this->createTeam('TRN-TEAM-6', 'Validation FC');

        $this->postJson('/api/sport/training-sessions', [
            'team_id' => $team->id,
            'title' => 'Invalid training',
            'training_type' => 'invalid',
            'starts_at' => '2026-08-01 11:00:00',
            'ends_at' => '2026-08-01 10:00:00',
            'intensity' => 'extreme',
            'status' => 'unknown',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.training_type.0', 'The selected training type is invalid.')
            ->assertJsonPath('data.errors.ends_at.0', 'The ends at field must be a date after starts at.')
            ->assertJsonPath('data.errors.intensity.0', 'The selected intensity is invalid.')
            ->assertJsonPath('data.errors.status.0', 'The selected status is invalid.');
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

    private function createSession(SportTeam $team, User $admin): SportTrainingSession
    {
        return SportTrainingSession::query()->create($this->payload($team) + [
            'session_code' => 'TRN-'.strtoupper(substr(md5((string) $team->id), 0, 8)),
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    private function payload(SportTeam $team): array
    {
        return [
            'team_id' => $team->id,
            'title' => 'Technical training',
            'training_type' => 'technical',
            'focus' => 'Passing and ball control',
            'venue' => 'Main field',
            'starts_at' => '2026-08-01 08:00:00',
            'ends_at' => '2026-08-01 10:00:00',
            'intensity' => 'medium',
            'status' => 'scheduled',
            'notes' => 'Bring training equipment.',
        ];
    }
}
