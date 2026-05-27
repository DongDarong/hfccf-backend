<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\Role;
use App\Models\User;
use App\Support\ImageStorage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected const OTP_TEST_IP = '10.10.10.10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_can_log_in_and_receive_frontend_shaped_payload(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
            'remember' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.firstName', $user->first_name)
            ->assertJsonPath('data.user.lastName', $user->last_name)
            ->assertJsonPath('data.user.role', $user->role_code)
            ->assertJsonPath('data.user.departmentCode', 'operations')
            ->assertJsonPath('data.user.permissions.0', 'all:*');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_superadmin_login_uses_role_permissions_when_direct_permissions_are_missing(): void
    {
        $user = $this->createUserWithoutDirectPermissions();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
            'remember' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'superadmin')
            ->assertJsonPath('data.user.permissions.0', 'all:*');

        $token = $response->json('data.token');

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.permissions.0', 'all:*');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Invalid credentials.',
                'data' => null,
            ]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ]);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->createUser([
            'status' => 'inactive',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
        ])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'This account is not active.',
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
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.permissions.0', 'all:*');

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Logout successful.',
                'data' => null,
            ]);

        $this->app->forgetInstance('auth');

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

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ]);
    }

    public function test_user_list_returns_normalized_pagination_shape(): void
    {
        $user = $this->createUser();
        $token = $this->loginAndGetToken($user);

        $this->getJson('/api/users?page=1&per_page=5&search=Test&sort_by=created_at&sort_direction=desc', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination' => [
                        'page',
                        'perPage',
                        'total',
                        'totalPages',
                    ],
                ],
            ]);
    }

    public function test_role_permissions_endpoint_returns_permissions_for_selected_role(): void
    {
        $user = $this->createUser();
        $token = $this->loginAndGetToken($user);

        $this->getJson('/api/roles/adminenglish/permissions', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'adminenglish')
            ->assertJsonPath('data.permissions.0.code', 'dashboard:read');
    }

    public function test_user_creation_and_update_sync_permissions_from_role(): void
    {
        $user = $this->createUser();
        $token = $this->loginAndGetToken($user);

        $created = $this->postJson('/api/users', [
            'first_name' => 'Role',
            'last_name' => 'Tester',
            'email' => 'role.tester@hfccf.org',
            'phone' => '+855 12 888 888',
            'role' => 'adminenglish',
            'status' => 'active',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'adminenglish')
            ->assertJsonPath('data.user.permissions.0', 'dashboard:read');

        $createdUserId = $created->json('data.user.id');

        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'email' => 'role.tester@hfccf.org',
            'role_code' => 'adminenglish',
        ]);

        $this->getJson('/api/super-admin/users/'.$createdUserId, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $createdUserId)
            ->assertJsonPath('data.user.role', 'adminenglish')
            ->assertJsonPath('data.user.permissions.0', 'dashboard:read');

        $updated = $this->putJson('/api/users/'.$createdUserId, [
            'first_name' => 'Role',
            'last_name' => 'Tester',
            'email' => 'role.tester@hfccf.org',
            'phone' => '+855 12 888 888',
            'role' => 'coach',
            'department_code' => 'sports',
            'status' => 'active',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updated
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'coach')
            ->assertJsonPath('data.user.permissions.0', 'athletes:read');
    }

    public function test_forgot_password_does_not_reveal_email_existence(): void
    {
        Mail::fake();

        $existing = $this->otpPostJson('/api/auth/forgot-password', [
            'email' => 'superadmin01@hfccf.org',
        ]);

        $missing = $this->otpPostJson('/api/auth/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $existing
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email exists, a verification code has been sent.');

        $missing
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email exists, a verification code has been sent.');

        Mail::assertSentTimes(PasswordResetOtpMail::class, 1);
    }

    public function test_valid_otp_verification_and_reset_flow_succeeds(): void
    {
        $user = $this->createUser();
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $otpCode,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-secret-pass',
        ])->assertOk();
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = $this->createUser();
        [$otpCode, $otp] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $otp->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $otpCode,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_wrong_otp_increments_attempts_and_locks_after_five_failures(): void
    {
        $user = $this->createUser();
        $this->issueOtpForEmail($user->email);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->clearOtpRateLimit($user->email);
            $this->otpPostJson('/api/auth/verify-otp', [
                'email' => $user->email,
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $otp = PasswordResetOtp::query()
            ->where('email', $user->email)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(5, $otp->attempts);
        $this->assertSame('cancelled', $otp->status);

        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertUnprocessable();
    }

    public function test_reset_password_revalidates_otp_server_side(): void
    {
        $user = $this->createUser();
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '111111',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_used_otp_cannot_be_reused(): void
    {
        $user = $this->createUser();
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])->assertOk();

        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'another-secret-pass',
            'password_confirmation' => 'another-secret-pass',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_resend_invalidates_previous_active_otp(): void
    {
        $user = $this->createUser();
        [$firstOtpCode, $firstOtp] = $this->issueOtpForEmail($user->email);
        [$secondOtpCode, $secondOtp] = $this->issueOtpForEmail($user->email);

        $firstOtp->refresh();
        $secondOtp->refresh();

        $this->assertSame('cancelled', $firstOtp->status);
        $this->assertSame('pending', $secondOtp->status);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $firstOtpCode,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $secondOtpCode,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = $this->createUser();
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-secret-pass',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_update_profile_avatar_only(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        $originalImageDisk = config('filesystems.image_disk');
        $originalR2Url = config('filesystems.disks.r2.url');
        config([
            'filesystems.image_disk' => 'r2',
            'filesystems.disks.r2.url' => 'https://cdn.example.test',
        ]);

        try {
            $user = $this->createUser([
                'avatar' => null,
            ]);
            $token = $this->loginAndGetToken($user);
            $avatar = UploadedFile::fake()->image('avatar.jpg', 320, 320);

            $response = $this->post('/api/auth/me', [
                '_method' => 'PATCH',
                'avatar' => $avatar,
            ], [
                'Authorization' => 'Bearer '.$token,
            ]);

            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.user.id', $user->id);

            $avatarUrl = (string) $response->json('data.user.avatar');
            $this->assertNotEmpty($avatarUrl);
            $this->assertStringContainsString('/storage/avatars/', $avatarUrl);

            $avatarPath = ImageStorage::resolvePath($avatarUrl);
            $this->assertNotNull($avatarPath);
            Storage::disk('r2')->assertExists($avatarPath);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'avatar' => $avatarPath,
            ]);
        } finally {
            config([
                'filesystems.image_disk' => $originalImageDisk,
                'filesystems.disks.r2.url' => $originalR2Url,
            ]);
        }
    }

    public function test_authenticated_user_can_replace_avatar_and_remove_old_file(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        $originalImageDisk = config('filesystems.image_disk');
        $originalR2Url = config('filesystems.disks.r2.url');
        config([
            'filesystems.image_disk' => 'r2',
            'filesystems.disks.r2.url' => 'https://cdn.example.test',
        ]);

        try {
            Storage::disk('r2')->put('avatars/old-avatar.jpg', 'old-avatar');

            $oldAvatarUrl = Storage::disk('r2')->url('avatars/old-avatar.jpg');
            $user = $this->createUser([
                'avatar' => $oldAvatarUrl,
            ]);
            $token = $this->loginAndGetToken($user);
            $avatar = UploadedFile::fake()->image('new-avatar.jpg', 320, 320);

            $response = $this->post('/api/auth/me', [
                '_method' => 'PATCH',
                'avatar' => $avatar,
            ], [
                'Authorization' => 'Bearer '.$token,
            ]);

            $response->assertOk()->assertJsonPath('success', true);

            Storage::disk('r2')->assertMissing('avatars/old-avatar.jpg');

            $newAvatarUrl = (string) $response->json('data.user.avatar');
            $newAvatarPath = ImageStorage::resolvePath($newAvatarUrl);
            $this->assertNotNull($newAvatarPath);
            Storage::disk('r2')->assertExists($newAvatarPath);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'avatar' => $newAvatarPath,
            ]);
        } finally {
            config([
                'filesystems.image_disk' => $originalImageDisk,
                'filesystems.disks.r2.url' => $originalR2Url,
            ]);
        }
    }

    public function test_authenticated_user_can_remove_existing_avatar(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        $originalImageDisk = config('filesystems.image_disk');
        $originalR2Url = config('filesystems.disks.r2.url');
        config([
            'filesystems.image_disk' => 'r2',
            'filesystems.disks.r2.url' => 'https://cdn.example.test',
        ]);

        try {
            Storage::disk('r2')->put('avatars/remove-avatar.jpg', 'avatar');

            $avatarUrl = Storage::disk('r2')->url('avatars/remove-avatar.jpg');
            $user = $this->createUser([
                'avatar' => $avatarUrl,
            ]);
            $token = $this->loginAndGetToken($user);

            $this->patchJson('/api/auth/me', [
                'remove_avatar' => true,
            ], [
                'Authorization' => 'Bearer '.$token,
            ])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.user.avatar', null);

            Storage::disk('r2')->assertMissing('avatars/remove-avatar.jpg');
            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'avatar' => null,
            ]);
        } finally {
            config([
                'filesystems.image_disk' => $originalImageDisk,
                'filesystems.disks.r2.url' => $originalR2Url,
            ]);
        }
    }

    public function test_authenticated_user_can_store_avatar_on_r2_disk_without_changing_response_shape(): void
    {
        Storage::fake('public');
        Storage::fake('r2');

        $originalImageDisk = config('filesystems.image_disk');
        $originalR2Url = config('filesystems.disks.r2.url');

        config([
            'filesystems.image_disk' => 'r2',
            'filesystems.disks.r2.url' => 'https://cdn.example.test',
        ]);

        try {
            $user = $this->createUser([
                'avatar' => null,
            ]);
            $token = $this->loginAndGetToken($user);
            $avatar = UploadedFile::fake()->image('avatar.jpg', 320, 320);

            $response = $this->post('/api/auth/me', [
                '_method' => 'PATCH',
                'avatar' => $avatar,
            ], [
                'Authorization' => 'Bearer '.$token,
            ]);

            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.user.id', $user->id);

            $avatarUrl = (string) $response->json('data.user.avatar');

            $avatarPath = ImageStorage::resolvePath($avatarUrl);
            $this->assertNotNull($avatarPath);
            $this->assertSame(Storage::disk('r2')->url($avatarPath), $avatarUrl);
            Storage::disk('r2')->assertExists($avatarPath);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'avatar' => $avatarPath,
            ]);
        } finally {
            config([
                'filesystems.image_disk' => $originalImageDisk,
                'filesystems.disks.r2.url' => $originalR2Url,
            ]);
        }
    }

    public function test_authenticated_user_avatar_upload_rejects_invalid_file_and_oversized_image(): void
    {
        $user = $this->createUser([
            'avatar' => null,
        ]);
        $token = $this->loginAndGetToken($user);

        $this->post('/api/auth/me', [
            '_method' => 'PATCH',
            'avatar' => UploadedFile::fake()->create('avatar.txt', 4, 'text/plain'),
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->post('/api/auth/me', [
            '_method' => 'PATCH',
            'avatar' => UploadedFile::fake()->image('large-avatar.jpg')->size(2050),
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_update_department_code_and_partial_profile_fields(): void
    {
        $user = $this->createUser([
            'department_code' => 'operations',
        ]);
        $token = $this->loginAndGetToken($user);

        $this->patchJson('/api/auth/me', [
            'first_name' => 'Updated',
            'department_code' => 'education',
            'bio' => 'Updated bio',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.firstName', 'Updated')
            ->assertJsonPath('data.user.departmentCode', 'education')
            ->assertJsonPath('data.user.bio', 'Updated bio');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Updated',
            'last_name' => $user->last_name,
            'email' => $user->email,
            'department_code' => 'education',
            'bio' => 'Updated bio',
        ]);
    }

    public function test_authenticated_user_rejects_invalid_department_code_on_profile_update(): void
    {
        $user = $this->createUser();
        $token = $this->loginAndGetToken($user);

        $this->patchJson('/api/auth/me', [
            'department_code' => 'invalid-department',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'errors' => [
                        'department_code',
                    ],
                ],
            ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = $this->createUser();
        $token = $this->loginAndGetToken($user);

        $this->patchJson('/api/auth/change-password', [
            'current_password' => 'secret-pass',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-secret-pass',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_change_password_rejects_wrong_current_password(): void
    {
        $user = $this->createUser();
        $token = $this->loginAndGetToken($user);

        $this->patchJson('/api/auth/change-password', [
            'current_password' => 'wrong-pass',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_change_password_requires_confirmation(): void
    {
        $user = $this->createUser();
        $token = $this->loginAndGetToken($user);

        $this->patchJson('/api/auth/change-password', [
            'current_password' => 'secret-pass',
            'password' => 'new-secret-pass',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'errors' => [
                        'password',
                    ],
                ],
            ]);
    }

    private function createUser(array $overrides = []): User
    {
        $role = Role::query()->findOrFail('superadmin');

        $user = User::query()->create(array_merge([
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
        ], $overrides));

        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission_code' => 'all:*',
        ]);

        return $user;
    }

    private function createUserWithoutDirectPermissions(array $overrides = []): User
    {
        $role = Role::query()->findOrFail('superadmin');

        return User::query()->create(array_merge([
            'id' => 'usr-role-only',
            'first_name' => 'Role',
            'last_name' => 'Only',
            'username' => 'Role Only',
            'email' => 'role.only@hfccf.org',
            'phone' => '+855 12 777 777',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'avatar' => null,
            'password' => 'secret-pass',
        ], $overrides));
    }

    protected function loginAndGetToken(User $user, string $password = 'secret-pass'): string
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
            'remember' => true,
        ]);

        return (string) $response->json('data.token');
    }

    /**
     * @return array{0: string, 1: PasswordResetOtp}
     */
    protected function issueOtpForEmail(string $email): array
    {
        Mail::fake();
        $this->clearOtpRateLimit($email);

        $this->otpPostJson('/api/auth/forgot-password', [
            'email' => $email,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $otpCode = null;

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otpCode): bool {
            $otpCode = $mail->otp;

            return true;
        });

        $otp = PasswordResetOtp::query()
            ->where('email', $email)
            ->latest('id')
            ->firstOrFail();

        return [$otpCode, $otp];
    }

    protected function clearOtpRateLimit(string $email): void
    {
        $normalizedEmail = strtolower($email);

        RateLimiter::clear('otp:email:'.$normalizedEmail);
        RateLimiter::clear('otp:ip:'.self::OTP_TEST_IP);
    }

    protected function otpPostJson(string $uri, array $data = [])
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => self::OTP_TEST_IP,
        ])->postJson($uri, $data);
    }
}
