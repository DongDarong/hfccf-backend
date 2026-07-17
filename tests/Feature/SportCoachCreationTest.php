<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ImageStorage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportCoachCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminsport_can_create_a_coach_with_avatar_and_permissions(): void
    {
        $admin = $this->createUserWithRole('adminsport', [
            'id' => 'usr_990',
            'email' => 'sport.admin990@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        Storage::fake(ImageStorage::diskName());

        $avatar = UploadedFile::fake()->image('coach-avatar.png', 320, 320);

        $response = $this->post('/api/sport/coaches', [
            'name' => 'Coach Alpha',
            'email' => 'coach.alpha@hfccf.org',
            'phone' => '+855 12 900 900',
            'status' => 'active',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'avatar' => $avatar,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coach.fullName', 'Coach Alpha')
            ->assertJsonPath('data.coach.role', 'coach')
            ->assertJsonPath('data.coach.status', 'active')
            ->assertJsonCount(3, 'data.coach.permissions');

        $coachId = (string) $response->json('data.coach.id');
        $this->assertMatchesRegularExpression('/^usr_\d+$/', $coachId);
        $this->assertContains('dashboard:read', $response->json('data.coach.permissions'));
        $this->assertContains('athletes:read', $response->json('data.coach.permissions'));
        $this->assertContains('training:write', $response->json('data.coach.permissions'));

        $this->assertDatabaseHas('users', [
            'id' => $coachId,
            'email' => 'coach.alpha@hfccf.org',
            'role_code' => 'coach',
            'department_code' => 'sports',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $coachId,
            'permission_code' => 'dashboard:read',
        ]);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $coachId,
            'permission_code' => 'athletes:read',
        ]);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $coachId,
            'permission_code' => 'training:write',
        ]);

        $avatarUrl = (string) $response->json('data.coach.avatar');
        $avatarPath = ImageStorage::resolvePath($avatarUrl);

        $this->assertNotNull($avatarPath);
        Storage::disk(ImageStorage::diskName())->assertExists($avatarPath);
    }

    public function test_adminsport_validation_rejects_single_word_names_with_field_errors(): void
    {
        $admin = $this->createUserWithRole('adminsport', [
            'id' => 'usr_991',
            'email' => 'sport.admin991@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/sport/coaches', [
            'name' => 'Coach',
            'email' => 'coach.single@hfccf.org',
            'phone' => '+855 12 991 991',
            'status' => 'active',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The last name field is required.')
            ->assertJsonPath('data.errors.last_name.0', 'The last name field is required.');
    }

    public function test_failed_permission_sync_rolls_back_the_coach_user(): void
    {
        $admin = $this->createUserWithRole('adminsport', [
            'id' => 'usr_992',
            'email' => 'sport.admin992@hfccf.org',
        ]);
        Sanctum::actingAs($admin);

        User::created(function (User $user) {
            if ($user->email === 'rollback.coach@hfccf.org') {
                throw new \RuntimeException('Coach sync failed.');
            }
        });

        $response = $this->postJson('/api/sport/coaches', [
            'name' => 'Rollback Coach',
            'email' => 'rollback.coach@hfccf.org',
            'phone' => '+855 12 992 992',
            'status' => 'active',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertStatus(500);

        $this->assertDatabaseMissing('users', [
            'email' => 'rollback.coach@hfccf.org',
        ]);
    }
}
