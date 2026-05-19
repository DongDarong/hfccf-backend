<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(DatabaseSeeder::class);
    }

    // ─── OTP issuance: all active roles should receive a code ────────────────────

    #[DataProvider('activeRoleProvider')]
    public function test_active_user_of_any_role_can_receive_otp(string $roleCode): void
    {
        Mail::fake();
        $user = $this->createUserWithRole($roleCode);

        $this->otpPostJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email exists, a verification code has been sent.')
            ->assertJsonStructure(['data' => ['expiresAt']]);

        Mail::assertSentTimes(PasswordResetOtpMail::class, 1);
    }

    public static function activeRoleProvider(): array
    {
        return [
            'superadmin' => ['superadmin'],
            'adminenglish' => ['adminenglish'],
            'adminpreschool' => ['adminpreschool'],
            'adminscholarship' => ['adminscholarship'],
            'adminsport' => ['adminsport'],
            'coach' => ['coach'],
            'teacher-english' => ['teacher-english'],
            'teacher-preschool' => ['teacher-preschool'],
        ];
    }

    // ─── Ineligible accounts must not leak email existence ───────────────────────

    public function test_suspended_user_does_not_receive_otp(): void
    {
        Mail::fake();
        $user = $this->createUserWithRole('adminenglish', ['status' => 'inactive']);

        $this->otpPostJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If the email exists, a verification code has been sent.');

        Mail::assertNothingSent();
    }

    public function test_soft_deleted_user_does_not_receive_otp(): void
    {
        Mail::fake();
        $user = $this->createUserWithRole('adminenglish');
        $user->delete();

        $this->otpPostJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertNothingSent();
    }

    public function test_unknown_email_does_not_receive_otp(): void
    {
        Mail::fake();

        $this->otpPostJson('/api/auth/forgot-password', ['email' => 'no-such-user@example.com'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertNothingSent();
    }

    // ─── Full reset flow for non-superadmin roles ─────────────────────────────────

    public function test_adminenglish_can_complete_full_password_reset(): void
    {
        $user = $this->createUserWithRole('adminenglish', ['password' => 'old-password']);
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $otpCode,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'OTP verified successfully.');

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-password-abc',
            'password_confirmation' => 'new-password-abc',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-abc',
        ])->assertOk();
    }

    public function test_coach_can_complete_full_password_reset(): void
    {
        $user = $this->createUserWithRole('coach', ['password' => 'old-password']);
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-password-abc',
            'password_confirmation' => 'new-password-abc',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-abc',
        ])->assertOk();
    }

    public function test_reset_revokes_all_existing_tokens(): void
    {
        $user = $this->createUserWithRole('adminenglish', ['password' => 'old-password']);
        $token = $this->loginAndGetToken($user, 'old-password');

        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-password-abc',
            'password_confirmation' => 'new-password-abc',
        ])->assertOk();

        $this->app->forgetInstance('auth');

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$token])
            ->assertUnauthorized();
    }

    // ─── OTP edge cases ───────────────────────────────────────────────────────────

    public function test_expired_otp_is_rejected_for_regular_role(): void
    {
        $user = $this->createUserWithRole('adminenglish');
        [$otpCode, $otp] = $this->issueOtpForEmail($user->email);

        $otp->forceFill(['expires_at' => now()->subMinute()])->save();

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
            'password' => 'new-password-abc',
            'password_confirmation' => 'new-password-abc',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_wrong_otp_locks_after_five_failures_for_regular_role(): void
    {
        $user = $this->createUserWithRole('coach');
        $this->issueOtpForEmail($user->email);

        for ($i = 1; $i <= 5; $i++) {
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

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertUnprocessable();
    }

    public function test_used_otp_cannot_be_reused_for_regular_role(): void
    {
        $user = $this->createUserWithRole('adminpreschool', ['password' => 'old-password']);
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-password-abc',
            'password_confirmation' => 'new-password-abc',
        ])->assertOk();

        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_resend_cancels_previous_otp_for_regular_role(): void
    {
        $user = $this->createUserWithRole('adminsport');
        [$firstCode, $firstOtp] = $this->issueOtpForEmail($user->email);
        [, $secondOtp] = $this->issueOtpForEmail($user->email);

        $firstOtp->refresh();
        $secondOtp->refresh();

        $this->assertSame('cancelled', $firstOtp->status);
        $this->assertSame('pending', $secondOtp->status);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => $firstCode,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_forgot_password_validation_requires_valid_email(): void
    {
        $this->otpPostJson('/api/auth/forgot-password', ['email' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->otpPostJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_reset_password_validation_requires_confirmation(): void
    {
        $user = $this->createUserWithRole('teacher-english');
        [$otpCode] = $this->issueOtpForEmail($user->email);

        $this->clearOtpRateLimit($user->email);
        $this->otpPostJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $otpCode,
            'password' => 'new-password-abc',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }
}
