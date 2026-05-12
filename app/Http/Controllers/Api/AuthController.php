<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\PersonalAccessToken;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use App\Mail\PasswordResetOtpMail;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    private const RECOVERY_ROLE = 'superadmin';

    private const RECOVERY_PERMISSION = 'all:*';

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $remember = (bool) ($credentials['remember'] ?? false);

        $user = User::query()
            ->with([
                'department',
                'role',
                'permissions' => fn ($query) => $query->orderBy('permissions.code'),
            ])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This account is not active.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        // "Remember me" issues a longer-lived API token.
        // Frontend still decides whether to persist the token in localStorage or sessionStorage.
        $expiresAt = $remember ? now()->addDays(30) : now()->addHours(12);
        [$token, $plainTextToken] = PersonalAccessToken::issueFor($user, 'auth-token', $expiresAt);

        $user->load([
            'department',
            'role',
            'permissions' => fn ($query) => $query->orderBy('permissions.code'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $plainTextToken,
                'expiresAt' => $token->expires_at?->toISOString(),
                'user' => AuthUserResource::make($user)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function forgotPassword(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => ['required', 'email'],
    ]);

    $user = $this->findEligibleRecoveryUser($validated['email']);

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Only an active Super Admin account can reset a password.',
            'data' => null,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    PasswordResetOtp::query()
        ->where('user_id', $user->id)
        ->where('status', 'pending')
        ->update(['status' => 'cancelled']);

    $plainOtp = (string) random_int(100000, 999999);

    $otp = PasswordResetOtp::query()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'otp_hash' => Hash::make($plainOtp),
        'purpose' => 'forgot_password',
        'channel' => 'email',
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
        'last_sent_at' => now(),
        'request_ip' => $request->ip(),
        'user_agent' => substr((string) $request->userAgent(), 0, 255),
    ]);

    Mail::to($user->email)->send(
        new PasswordResetOtpMail($plainOtp, $user->email)
    );

    return response()->json([
        'success' => true,
        'message' => 'OTP sent successfully.',
        'data' => [
            'email' => $otp->email,
            'expiresAt' => $otp->expires_at?->toISOString(),
        ],
    ], Response::HTTP_OK);
}

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $otp = $this->latestPendingOtp($validated['email']);

        if (! $otp) {
            return $this->invalidOtpResponse();
        }

        if ($otp->expires_at->isPast()) {
            $otp->forceFill(['status' => 'expired'])->save();

            return $this->invalidOtpResponse('OTP has expired.');
        }

        if (! Hash::check($validated['code'], $otp->otp_hash)) {
            $otp->increment('attempts');

            if ($otp->attempts >= $otp->max_attempts) {
                $otp->forceFill(['status' => 'cancelled'])->save();
            }

            return $this->invalidOtpResponse();
        }

        $otp->forceFill([
            'status' => 'verified',
            'verified_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'email' => $otp->email,
                'verifiedAt' => $otp->verified_at?->toISOString(),
            ],
        ], Response::HTTP_OK);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $otp = PasswordResetOtp::query()
            ->with('user')
            ->where('email', strtolower($validated['email']))
            ->where('purpose', 'forgot_password')
            ->where('status', 'verified')
            ->latest('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast() || ! Hash::check($validated['code'], $otp->otp_hash)) {
            return $this->invalidOtpResponse('Verify OTP before creating a new password.');
        }

        $otp->user->forceFill([
            'password' => $validated['password'],
        ])->save();

        $otp->forceFill([
            'status' => 'used',
            'used_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->loadMissing([
            'department',
            'role',
            'permissions' => fn ($query) => $query->orderBy('permissions.code'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data' => AuthUserResource::make($user)->resolve($request),
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\PersonalAccessToken|null $accessToken */
        $accessToken = $request->attributes->get('accessToken');

        if ($accessToken) {
            $accessToken->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function findEligibleRecoveryUser(string $email): ?User
    {
        $user = User::query()
            ->with('permissions')
            ->where('email', strtolower($email))
            ->where('role_code', self::RECOVERY_ROLE)
            ->where('status', 'active')
            ->first();

        if (! $user || ! $user->permissions->contains('code', self::RECOVERY_PERMISSION)) {
            return null;
        }

        return $user;
    }

    private function latestPendingOtp(string $email): ?PasswordResetOtp
    {
        return PasswordResetOtp::query()
            ->where('email', strtolower($email))
            ->where('purpose', 'forgot_password')
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    private function invalidOtpResponse(string $message = 'Invalid OTP code.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
