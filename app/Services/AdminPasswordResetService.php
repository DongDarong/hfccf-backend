<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationTarget;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPasswordResetService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function reset(User $actor, User $target, string $password, string $reason, Request $request): User
    {
        $this->assertCanReset($actor, $target);

        $actor->loadMissing(['role']);
        $target->loadMissing(['role']);

        return DB::transaction(function () use ($actor, $target, $password, $reason, $request): User {
            $timestamp = now();

            $target->forceFill([
                'password' => $password,
                'must_change_password' => true,
                'password_changed_at' => $timestamp,
                'last_password_reset_at' => $timestamp,
                'last_password_reset_by' => $actor->id,
            ])->save();

            $target->tokens()->delete();

            $this->auditLogService->record([
                'actor_user_id' => $actor->id,
                'domain' => 'auth',
                'action' => 'PASSWORD_RESET',
                'entity_type' => 'user',
                'entity_id' => $target->id,
                'entity_label' => trim(($target->first_name ?? '').' '.($target->last_name ?? '')) ?: $target->username ?: $target->email,
                'metadata' => [
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role_code,
                    'target_user_id' => $target->id,
                    'target_role' => $target->role_code,
                    'timestamp' => $timestamp->toISOString(),
                    'reason' => $reason,
                    'source_ip' => $request->ip(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'created_at' => $timestamp,
            ]);

            $this->createNotification($actor, $target, $reason, $timestamp, $request);

            return $target->refresh()->loadMissing([
                'department',
                'lastPasswordResetBy',
                'permissions' => fn ($query) => $query->orderBy('permissions.code'),
                'role',
            ]);
        });
    }

    public function assertCanReset(User $actor, User $target): void
    {
        $actor->loadMissing(['role']);
        $target->loadMissing(['role']);

        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => ['Use the self-service change password endpoint for your own account.'],
            ]);
        }

        if ($actor->role_code === 'superadmin' || $this->hasPermission($actor, 'all:*')) {
            return;
        }

        $allowedTargets = $this->allowedTargetRolesFor($actor);

        if (! in_array($target->role_code, $allowedTargets, true)) {
            throw new AuthorizationException('You are not allowed to reset this account password.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedTargetRolesFor(User $actor): array
    {
        return match ($actor->role_code) {
            'adminpreschool' => ['teacher-preschool'],
            'adminenglish' => ['teacher-english'],
            'adminsport' => ['coach'],
            'adminscholarship' => ['teacher-scholarship'],
            default => [],
        };
    }

    private function createNotification(User $actor, User $target, string $reason, \DateTimeInterface $timestamp, Request $request): void
    {
        $module = $target->role?->domain_code ?: 'global';
        $title = 'Password Reset';
        $message = 'Your account password was reset by an administrator.';

        $notification = Notification::query()->create([
            'type' => 'warning',
            'title' => $title,
            'message' => $message,
            'module' => $module,
            'action_url' => null,
            'metadata' => [
                'reason' => $reason,
                'administrator_id' => $actor->id,
                'administrator_name' => trim($actor->first_name.' '.$actor->last_name) ?: $actor->username,
                'target_user_id' => $target->id,
                'target_role' => $target->role_code,
                'date' => $timestamp->format(DATE_ATOM),
            ],
            'created_by' => $actor->id,
        ]);

        NotificationTarget::query()->create([
            'notification_id' => $notification->id,
            'target_type' => 'user',
            'target_value' => $target->id,
        ]);

        NotificationRecipient::query()->create([
            'notification_id' => $notification->id,
            'user_id' => $target->id,
        ]);
    }

    private function hasPermission(User $user, string $permissionCode): bool
    {
        $permissionCodes = $user->relationLoaded('permissions')
            ? $user->permissions->pluck('code')->all()
            : $user->permissions()->pluck('permissions.code')->all();

        return in_array('all:*', $permissionCodes, true) || in_array($permissionCode, $permissionCodes, true);
    }
}
