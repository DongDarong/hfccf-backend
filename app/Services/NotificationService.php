<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationTarget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createNotification(User $creator, array $data): Notification
    {
        $creator->loadMissing('role', 'permissions');

        $this->authorizeCreator($creator, $data);

        $targetType = (string) $data['target_type'];
        $targetValue = isset($data['target_value']) ? trim((string) $data['target_value']) : null;
        $targetValue = $targetValue === '' ? null : $targetValue;
        $module = (string) $data['module'];

        return DB::transaction(function () use ($creator, $data, $targetType, $targetValue, $module): Notification {
            $users = $this->resolveTargetUsers($targetType, $targetValue);

            if ($users->isEmpty()) {
                throw ValidationException::withMessages([
                    'target_value' => ['No active users matched the selected notification target.'],
                ]);
            }

            $notification = Notification::query()->create([
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'module' => $module,
                'action_url' => $data['action_url'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $creator->id,
            ]);

            NotificationTarget::query()->create([
                'notification_id' => $notification->id,
                'target_type' => $targetType,
                'target_value' => $targetValue,
            ]);

            $timestamp = now();
            $recipientRows = $users->map(static function (User $user) use ($notification, $timestamp): array {
                return [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'created_at' => $timestamp,
                ];
            })->all();

            NotificationRecipient::query()->insert($recipientRows);

            return $notification->load(['creator', 'targets']);
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveTargetUsers(string $targetType, ?string $targetValue): Collection
    {
        return match ($targetType) {
            'all' => User::query()
                ->where('status', 'active')
                ->get(),
            'role' => User::query()
                ->with('role')
                ->where('status', 'active')
                ->where('role_code', $targetValue)
                ->get(),
            'department' => User::query()
                ->where('status', 'active')
                ->where('department_code', $targetValue)
                ->get(),
            'module' => User::query()
                ->with('role')
                ->where('status', 'active')
                ->whereHas('role', static function ($query) use ($targetValue): void {
                    $query->where('domain_code', $targetValue);
                })
                ->get(),
            'user' => User::query()
                ->with('role')
                ->where('status', 'active')
                ->where('id', $targetValue)
                ->get(),
            default => User::query()->whereRaw('1 = 0')->get(),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizeCreator(User $creator, array $data): void
    {
        $targetType = (string) $data['target_type'];
        $targetValue = isset($data['target_value']) ? trim((string) $data['target_value']) : null;
        $creatorModule = $creator->role?->domain_code;
        $isSuperAdmin = $creator->role_code === 'superadmin' || $this->hasPermission($creator, 'all:*');

        if ($isSuperAdmin) {
            if ($targetType === 'all' && ($data['module'] ?? null) !== 'global') {
                throw new AuthorizationException('Global notifications must use the global module.');
            }

            if ($targetType === 'department' && ($data['module'] ?? null) !== 'global') {
                throw new AuthorizationException('Department notifications must use the global module.');
            }

            return;
        }

        if ($creatorModule === null) {
            throw new AuthorizationException('You are not allowed to create notifications.');
        }

        if (($data['module'] ?? null) !== $creatorModule) {
            throw new AuthorizationException('You can only create notifications for your own module.');
        }

        if (in_array($targetType, ['all', 'department'], true)) {
            throw new AuthorizationException('You can only target users within your own module.');
        }

        if ($targetType === 'module' && $targetValue !== $creatorModule) {
            throw new AuthorizationException('You can only target your own module.');
        }

        if ($targetType === 'role') {
            $role = Role::query()->where('code', $targetValue)->first();

            if (! $role || $role->domain_code !== $creatorModule) {
                throw new AuthorizationException('You can only target roles in your own module.');
            }

            return;
        }

        if ($targetType === 'user') {
            $targetUser = User::query()->with('role')->where('id', $targetValue)->first();

            if (! $targetUser || $targetUser->role?->domain_code !== $creatorModule) {
                throw new AuthorizationException('You can only target users in your own module.');
            }
        }
    }

    private function hasPermission(User $user, string $permissionCode): bool
    {
        $permissionCodes = $user->relationLoaded('permissions')
            ? $user->permissions->pluck('code')->all()
            : $user->permissions()->pluck('permissions.code')->all();

        return in_array('all:*', $permissionCodes, true) || in_array($permissionCode, $permissionCodes, true);
    }
}
