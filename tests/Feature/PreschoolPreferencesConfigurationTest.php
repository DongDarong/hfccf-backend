<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolPreferencesConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_superadmin_can_read_update_and_restore_preferences(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin', 'usr_prefs_100', 'superadmin.prefs100@hfccf.org');
        Sanctum::actingAs($superadmin);

        $initial = $this->getJson('/api/preschool/settings/backbone')
            ->assertOk()
            ->json('data.settings.preferences');

        $originalLanguage = $initial['defaultLanguage'] ?? 'en';
        $updatedLanguage = $originalLanguage === 'kh' ? 'en' : 'kh';

        $this->patchJson('/api/preschool/settings/backbone', [
            'preferences' => [
                'defaultLanguage' => $updatedLanguage,
            ],
        ])->assertOk()
            ->assertJsonPath('data.settings.preferences.defaultLanguage', $updatedLanguage);

        $this->getJson('/api/preschool/settings/backbone')
            ->assertOk()
            ->assertJsonPath('data.settings.preferences.defaultLanguage', $updatedLanguage);

        $this->patchJson('/api/preschool/settings/backbone', [
            'preferences' => [
                'defaultLanguage' => $originalLanguage,
            ],
        ])->assertOk()
            ->assertJsonPath('data.settings.preferences.defaultLanguage', $originalLanguage);
    }

    public function test_adminpreschool_can_read_and_update_preferences(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_prefs_101', 'admin.prefs101@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/settings/backbone')
            ->assertOk()
            ->assertJsonPath('data.settings.preferences.timezone', 'Asia/Phnom_Penh');

        $this->patchJson('/api/preschool/settings/backbone', [
            'preferences' => [
                'defaultClassCapacity' => 24,
            ],
        ])->assertOk()
            ->assertJsonPath('data.settings.preferences.defaultClassCapacity', 24);
    }

    public function test_teacher_preschool_can_read_but_cannot_update_preferences(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_prefs_102', 'teacher.prefs102@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/settings/backbone')
            ->assertOk()
            ->assertJsonPath('data.settings.preferences.studentCodePrefix', 'PS');

        $this->patchJson('/api/preschool/settings/backbone', [
            'preferences' => [
                'defaultLanguage' => 'kh',
            ],
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_denied(): void
    {
        $this->getJson('/api/preschool/settings/backbone')->assertUnauthorized();
        $this->patchJson('/api/preschool/settings/backbone', [
            'preferences' => [
                'defaultLanguage' => 'kh',
            ],
        ])->assertUnauthorized();
    }

    public function test_preferences_validation_rejects_invalid_values(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_prefs_103', 'admin.prefs103@hfccf.org');
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/preschool/settings/backbone', [
            'preferences' => [
                'timezone' => 'Mars/Phobos',
                'defaultLanguage' => 'es',
                'dateFormat' => 'invalid',
                'timeFormat' => 'bad',
                'minimumEnrollmentAgeMonths' => 72,
                'maximumEnrollmentAgeMonths' => 24,
                'studentCodeSequenceLength' => 0,
                'defaultClassCapacity' => 0,
                'teacherStudentRatio' => 0,
                'minimumGuardians' => 3,
                'maximumGuardians' => 1,
                'studentCodeYearFormat' => 'YYY',
            ],
        ]);

        $response->assertUnprocessable();

        $errors = $response->json('data.errors');

        $this->assertArrayHasKey('preferences.timezone', $errors);
        $this->assertArrayHasKey('preferences.defaultLanguage', $errors);
        $this->assertArrayHasKey('preferences.dateFormat', $errors);
        $this->assertArrayHasKey('preferences.timeFormat', $errors);
        $this->assertArrayHasKey('preferences.studentCodeYearFormat', $errors);
        $this->assertArrayHasKey('preferences.studentCodeSequenceLength', $errors);
        $this->assertArrayHasKey('preferences.defaultClassCapacity', $errors);
        $this->assertArrayHasKey('preferences.teacherStudentRatio', $errors);
        $this->assertArrayHasKey('preferences.maximumEnrollmentAgeMonths', $errors);
        $this->assertArrayHasKey('preferences.maximumGuardians', $errors);
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => ucfirst(str_replace('-', ' ', $roleCode)).' User',
            'email' => $email,
            'phone' => '+855 12 555 555',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        $rows = $role->permissions->map(static fn ($permission) => [
            'user_id' => $user->id,
            'permission_code' => $permission->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }

        return $user;
    }
}
