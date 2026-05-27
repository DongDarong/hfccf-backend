<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationTarget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SportNotificationDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyRole(string $roleCode, array $payload): ?Notification
    {
        $users = User::query()
            ->where('status', 'active')
            ->where('role_code', $roleCode)
            ->orderBy('id')
            ->get();

        return $this->createNotification($users, 'role', $roleCode, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyUser(User $user, array $payload): ?Notification
    {
        return $this->createNotification(collect([$user]), 'user', $user->id, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyUsers(Collection $users, string $targetType, ?string $targetValue, array $payload): ?Notification
    {
        return $this->createNotification($users, $targetType, $targetValue, $payload);
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $payload
     */
    private function createNotification(Collection $users, string $targetType, ?string $targetValue, array $payload): ?Notification
    {
        $users = $users->filter(static fn (User $user): bool => (string) $user->status === 'active')->unique('id')->values();

        if ($users->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($users, $targetType, $targetValue, $payload): Notification {
            $notification = Notification::query()->create([
                'type' => $payload['type'] ?? 'info',
                'title' => $payload['title'],
                'message' => $payload['message'],
                'module' => $payload['module'] ?? 'sport',
                'action_url' => $payload['action_url'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
                'created_by' => $payload['created_by'] ?? null,
            ]);

            NotificationTarget::query()->create([
                'notification_id' => $notification->id,
                'target_type' => $targetType,
                'target_value' => $targetValue,
            ]);

            $timestamp = now();
            NotificationRecipient::query()->insert($users->map(static function (User $user) use ($notification, $timestamp): array {
                return [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'created_at' => $timestamp,
                ];
            })->all());

            return $notification;
        });
    }
}
