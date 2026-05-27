<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Support\ImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    private const OTP_PURPOSE = 'forgot_password';

    private const OTP_STATUS_ACTIVE = ['pending', 'verified'];

    private const OTP_STATUS_TERMINAL = ['used', 'expired', 'cancelled'];

    private const OTP_MAX_ATTEMPTS = 5;

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
            ->where('email', strtolower($credentials['email']))
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

        if ($user->role_code === 'guardian') {
            return response()->json([
                'success' => false,
                'message' => 'Guardian portal access is disabled.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        // Sanctum Bearer token ("Authorization: Bearer {token}").
        // "Remember me" issues a longer-lived token; frontend still decides where to persist it.
        $expiresAt = $remember ? now()->addDays(30) : now()->addHours(12);
        $tokenResult = $user->createToken('auth-token', ['*'], $expiresAt);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $tokenResult->plainTextToken,
                'user' => UserResource::make($user)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $this->findEligibleRecoveryUser($validated['email']);

        if ($user) {
            $this->invalidateActiveOtps($user->email);

            $plainOtp = (string) random_int(100000, 999999);

            $otp = PasswordResetOtp::query()->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'otp_hash' => Hash::make($plainOtp),
                'purpose' => self::OTP_PURPOSE,
                'channel' => 'email',
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => self::OTP_MAX_ATTEMPTS,
                'resend_count' => 0,
                'expires_at' => now()->addMinutes(10),
                'last_sent_at' => now(),
                'request_ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            Mail::to($user->email)->send(
                new PasswordResetOtpMail($plainOtp, $user->email)
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'If the email exists, a verification code has been sent.',
            'data' => [
                'expiresAt' => now()->addMinutes(10)->toISOString(),
            ],
        ], Response::HTTP_OK);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $otp = $this->latestActiveOtp($validated['email']);

        if (! $otp) {
            return $this->invalidOtpResponse();
        }

        if ($this->otpIsExpired($otp)) {
            $this->expireOtp($otp);

            return $this->invalidOtpResponse();
        }

        if (! Hash::check($validated['code'], $otp->otp_hash)) {
            $this->registerFailedOtpAttempt($otp);

            return $this->invalidOtpResponse();
        }

        if ($otp->status === 'pending') {
            $otp->forceFill([
                'status' => 'verified',
                'verified_at' => now(),
            ])->save();
        }

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

        $otp = $this->latestActiveOtp($validated['email']);

        if (! $otp || ! $otp->user) {
            return $this->invalidOtpResponse();
        }

        if ($this->otpIsExpired($otp)) {
            $this->expireOtp($otp);

            return $this->invalidOtpResponse();
        }

        if (! Hash::check($validated['code'], $otp->otp_hash)) {
            $this->registerFailedOtpAttempt($otp);

            return $this->invalidOtpResponse();
        }

        $otp->user->forceFill([
            'password' => $validated['password'],
        ])->save();

        // Revoke all existing tokens after a password reset for safety.
        $otp->user->tokens()->delete();

        $otp->forceFill([
            'status' => 'used',
            'used_at' => now(),
            'verified_at' => $otp->verified_at ?? now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->loadMissing([
            'department',
            'role',
            'permissions' => fn ($query) => $query->orderBy('permissions.code'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('first_name', $data)) {
            $user->first_name = $data['first_name'];
        }

        if (array_key_exists('last_name', $data)) {
            $user->last_name = $data['last_name'];
        }

        if (array_key_exists('username', $data)) {
            $username = trim((string) $data['username']);
            $user->username = $username !== '' ? $username : trim($user->first_name.' '.$user->last_name);
        }

        if (array_key_exists('email', $data)) {
            $user->email = strtolower($data['email']);
        }

        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }

        if (array_key_exists('department_code', $data)) {
            $user->department_code = $data['department_code'];
        }

        if (array_key_exists('bio', $data)) {
            $user->bio = $data['bio'];
        }

        $replaceAvatar = $request->hasFile('avatar');
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);

        if ($replaceAvatar) {
            $this->deleteStoredAvatarIfNeeded($user->avatar);
            $user->avatar = $this->storeAvatarIfUploaded($request);
        } elseif ($removeAvatar) {
            $this->deleteStoredAvatarIfNeeded($user->avatar);
            $user->avatar = null;
        }

        $user->save();
        $user->loadMissing([
            'department',
            'role',
            'permissions' => fn ($query) => $query->orderBy('permissions.code'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $user->tokens()
                ->where('id', '!=', $currentToken->id)
                ->delete();
        }

        $user->loadMissing([
            'department',
            'role',
            'permissions' => fn ($query) => $query->orderBy('permissions.code'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $bearerToken = $request->bearerToken();

        // Prefer revoking the presented Bearer token.
        if ($bearerToken) {
            $token = PersonalAccessToken::findToken($bearerToken);

            if ($token && (! $user || (string) $token->tokenable_id === (string) $user->getKey())) {
                $token->delete();
            }
        } else {
            /** @var PersonalAccessToken|null $currentAccessToken */
            $currentAccessToken = $user?->currentAccessToken();
            $currentAccessToken?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
            'data' => null,
        ], Response::HTTP_OK);
    }

    private function findEligibleRecoveryUser(string $email): ?User
    {
        return User::query()
            ->where('email', strtolower($email))
            ->where('status', 'active')
            ->first();
    }

    private function invalidateActiveOtps(string $email): void
    {
        PasswordResetOtp::query()
            ->where('email', strtolower($email))
            ->where('purpose', self::OTP_PURPOSE)
            ->whereIn('status', self::OTP_STATUS_ACTIVE)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
    }

    private function latestActiveOtp(string $email): ?PasswordResetOtp
    {
        return PasswordResetOtp::query()
            ->with('user')
            ->where('email', strtolower($email))
            ->where('purpose', self::OTP_PURPOSE)
            ->whereIn('status', self::OTP_STATUS_ACTIVE)
            ->latest('id')
            ->first();
    }

    private function otpIsExpired(PasswordResetOtp $otp): bool
    {
        return $otp->expires_at === null || $otp->expires_at->isPast();
    }

    private function expireOtp(PasswordResetOtp $otp): void
    {
        $otp->forceFill([
            'status' => 'expired',
        ])->save();
    }

    private function registerFailedOtpAttempt(PasswordResetOtp $otp): void
    {
        $otp->forceFill([
            'attempts' => $otp->attempts + 1,
            'status' => $otp->attempts + 1 >= $otp->max_attempts ? 'cancelled' : $otp->status,
        ])->save();
    }

    private function invalidOtpResponse(string $message = 'Invalid OTP code.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function storeAvatarIfUploaded(Request $request): ?string
    {
        return ImageStorage::store($request->file('avatar'), 'avatars');
    }

    private function deleteStoredAvatarIfNeeded(?string $avatarUrl): void
    {
        ImageStorage::delete($avatarUrl);
    }
}
