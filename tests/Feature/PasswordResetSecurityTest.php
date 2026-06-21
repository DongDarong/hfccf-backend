<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_superadmin_can_reset_any_staff_password_and_user_is_forced_to_change_it(): void
    {
        $actor = $this->makeUserWithRole('superadmin', 'usr_9100', 'superadmin9100@hfccf.org');
        Sanctum::actingAs($actor);

        $target = $this->makeUserWithRole('teacher-english', 'usr_9101', 'teacher.9101@hfccf.org');

        $response = $this->postJson('/api/users/'.$target->id.'/reset-password', [
            'password' => 'Reset-Password-123',
            'password_confirmation' => 'Reset-Password-123',
            'reason' => 'Account handoff',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $target->id)
            ->assertJsonPath('data.user.mustChangePassword', true);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'must_change_password' => 1,
            'last_password_reset_by' => $actor->id,
        ]);

        $audit = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('PASSWORD_RESET', $audit->action);
        $this->assertSame($actor->id, $audit->actor_user_id);
        $this->assertSame($target->id, $audit->entity_id);
        $this->assertSame('Account handoff', $audit->metadata['reason']);
        $this->assertSame($actor->id, $audit->metadata['actor_id']);

        $notification = Notification::query()->latest('id')->firstOrFail();
        $this->assertSame('Password Reset', $notification->title);
        $this->assertSame('Your account password was reset by an administrator.', $notification->message);
        $this->assertSame('english', $notification->module);

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $target->id,
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $target->email,
            'password' => 'Reset-Password-123',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('data.requires_password_change', true)
            ->assertJsonPath('data.user.mustChangePassword', true);
    }

    #[DataProvider('sectionAdminResetProvider')]
    public function test_section_admin_can_reset_own_domain_staff(string $actorRole, string $targetRole, string $targetEmail): void
    {
        $actor = $this->makeUserWithRole($actorRole, 'usr_9200'.substr($actorRole, -1), strtolower($actorRole).'@hfccf.org');
        Sanctum::actingAs($actor);

        $target = $this->makeUserWithRole($targetRole, 'usr_9201'.substr($targetRole, -1), $targetEmail);

        $this->postJson('/api/users/'.$target->id.'/reset-password', [
            'password' => 'Reset-Password-123',
            'password_confirmation' => 'Reset-Password-123',
            'reason' => 'Operational reset',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.mustChangePassword', true);
    }

    public static function sectionAdminResetProvider(): array
    {
        return [
            'preschool' => ['adminpreschool', 'teacher-preschool', 'teacher.preschool9201@hfccf.org'],
            'english' => ['adminenglish', 'teacher-english', 'teacher.english9201@hfccf.org'],
            'sport' => ['adminsport', 'coach', 'coach9201@hfccf.org'],
            'scholarship' => ['adminscholarship', 'teacher-scholarship', 'teacher.scholarship9201@hfccf.org'],
        ];
    }

    public function test_section_admin_cannot_reset_users_outside_their_domain_or_superadmin(): void
    {
        $actor = $this->makeUserWithRole('adminpreschool', 'usr_9300', 'preschool.admin9300@hfccf.org');
        Sanctum::actingAs($actor);

        $crossDomainTarget = $this->makeUserWithRole('adminenglish', 'usr_9301', 'english.admin9301@hfccf.org');
        $superadminTarget = $this->makeUserWithRole('superadmin', 'usr_9302', 'superadmin9302@hfccf.org');

        $this->postJson('/api/users/'.$crossDomainTarget->id.'/reset-password', [
            'password' => 'Reset-Password-123',
            'password_confirmation' => 'Reset-Password-123',
            'reason' => 'Should be blocked',
        ])->assertForbidden();

        $this->postJson('/api/users/'.$superadminTarget->id.'/reset-password', [
            'password' => 'Reset-Password-123',
            'password_confirmation' => 'Reset-Password-123',
            'reason' => 'Should be blocked',
        ])->assertForbidden();
    }

    public function test_normal_update_endpoint_rejects_password_fields(): void
    {
        $actor = $this->makeUserWithRole('superadmin', 'usr_9400', 'superadmin9400@hfccf.org');
        Sanctum::actingAs($actor);

        $target = $this->makeUserWithRole('teacher-preschool', 'usr_9401', 'teacher.9401@hfccf.org');

        $this->putJson('/api/users/'.$target->id, [
            'first_name' => 'Updated',
            'password' => 'should-not-work',
            'password_confirmation' => 'should-not-work',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'errors' => [
                        'password',
                        'password_confirmation',
                    ],
                ],
            ]);
    }

    public function test_forced_password_change_blocks_normal_routes_until_completed(): void
    {
        $actor = $this->makeUserWithRole('superadmin', 'usr_9500', 'superadmin9500@hfccf.org');
        Sanctum::actingAs($actor);

        $target = $this->makeUserWithRole('teacher-english', 'usr_9501', 'teacher.9501@hfccf.org');

        $this->postJson('/api/users/'.$target->id.'/reset-password', [
            'password' => 'Reset-Password-123',
            'password_confirmation' => 'Reset-Password-123',
            'reason' => 'Policy test',
        ])->assertOk();

        $login = $this->postJson('/api/auth/login', [
            'email' => $target->email,
            'password' => 'Reset-Password-123',
        ]);

        $login->assertOk()->assertJsonPath('data.requires_password_change', true);

        Sanctum::actingAs(User::query()->findOrFail($target->id));

        $this->getJson('/api/english/teacher/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Password change required.');

        $this->patchJson('/api/auth/change-password', [
            'current_password' => 'Reset-Password-123',
            'password' => 'Final-Password-123',
            'password_confirmation' => 'Final-Password-123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs(User::query()->findOrFail($target->id));

        $this->getJson('/api/english/teacher/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'must_change_password' => 0,
        ]);
        $this->assertNotNull(User::query()->findOrFail($target->id)->password_changed_at);
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
