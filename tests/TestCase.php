<?php

namespace Tests;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    protected const OTP_TEST_IP = '10.10.10.10';

    protected function setUp(): void
    {
        parent::setUp();

        // Register a database listener to disable foreign keys for SQLite testing
        if (DB::getDriverName() === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys=OFF');
            } catch (\Exception $e) {
                // Ignore errors if statement fails
            }
        }
    }

    /**
     * Create a user with the given role and sync permissions from role_permissions.
     * Password defaults to 'password' — pass $overrides['password'] to change it.
     */
    protected function createUserWithRole(string $roleCode, array $overrides = []): User
    {
        $departmentCode = match ($roleCode) {
            'adminsport', 'coach' => 'sports',
            'superadmin' => 'operations',
            default => 'education',
        };

        $user = User::factory()->create(array_merge([
            'role_code' => $roleCode,
            'department_code' => $departmentCode,
            'status' => 'active',
        ], $overrides));

        $permissionCodes = DB::table('role_permissions')
            ->where('role_code', $roleCode)
            ->pluck('permission_code');

        foreach ($permissionCodes as $code) {
            DB::table('user_permissions')->insertOrIgnore([
                'user_id' => $user->id,
                'permission_code' => $code,
            ]);
        }

        return $user->fresh()->load('permissions');
    }

    /**
     * Log in as the given user and return the Bearer token.
     */
    protected function loginAndGetToken(User $user, string $password = 'password'): string
    {
        $this->flushHeaders();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        return (string) $response->json('data.token');
    }

    /**
     * Return $this with an Authorization header set for the given user.
     */
    protected function actingWithToken(User $user, string $password = 'password'): static
    {
        $token = $this->loginAndGetToken($user, $password);

        return $this->flushHeaders()->withToken($token);
    }

    /**
     * Trigger the forgot-password endpoint for $email, capture and return the OTP.
     *
     * @return array{0: string, 1: PasswordResetOtp}
     */
    protected function issueOtpForEmail(string $email): array
    {
        Mail::fake();
        $this->clearOtpRateLimit($email);

        $this->otpPostJson('/api/auth/forgot-password', ['email' => $email])
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
        $normalized = strtolower($email);

        RateLimiter::clear('otp:email:'.$normalized);
        RateLimiter::clear('otp:ip:'.self::OTP_TEST_IP);
    }

    protected function otpPostJson(string $uri, array $data = [])
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => self::OTP_TEST_IP,
        ])->postJson($uri, $data);
    }
}
