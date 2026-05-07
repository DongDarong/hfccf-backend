<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_can_log_in_and_receive_frontend_shaped_payload(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.firstName', $user->first_name)
            ->assertJsonPath('data.user.lastName', $user->last_name)
            ->assertJsonPath('data.user.role', $user->role_code)
            ->assertJsonPath('data.user.scope', 'super_admin')
            ->assertJsonPath('data.user.domain', 'global')
            ->assertJsonPath('data.user.departmentCode', 'operations')
            ->assertJsonPath('data.user.department', 'Operations');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(['all:*'], $response->json('data.user.role_permission'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Invalid credentials.',
                'data' => null,
            ]);
    }

    public function test_authenticated_user_can_be_retrieved_and_logged_out(): void
    {
        $user = $this->createUser();

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
        ]);

        $token = $login->json('data.token');

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role_permission.0', 'all:*');

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Logout successful.',
                'data' => null,
            ]);

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ]);
    }

    public function test_super_admin_can_complete_otp_password_reset_flow(): void
    {
        $user = $this->createUser();

        $forgot = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $otp = $forgot->json('data.demoOtp');

        $forgot
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email);

        $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otp,
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-secret-pass',
        ])->assertOk();
    }

    private function createUser(): User
    {
        $role = Role::query()->findOrFail('superadmin');

        $user = User::query()->create([
            'id' => 'usr_999',
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'username' => 'Test Admin',
            'email' => 'test.admin@hfccf.org',
            'phone' => '+855 12 999 999',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'avatar' => 'https://example.com/avatar.jpg',
            'password' => 'secret-pass',
        ]);

        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission_code' => 'all:*',
        ]);

        return $user;
    }
}
