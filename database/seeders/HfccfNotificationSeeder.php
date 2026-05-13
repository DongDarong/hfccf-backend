<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HfccfNotificationSeeder extends Seeder
{
    /**
     * Seed sample notification data for development and testing.
     */
    public function run(): void
    {
        DB::table('notification_recipients')->delete();
        DB::table('notification_targets')->delete();
        DB::table('notifications')->delete();

        $now = now();

        $notificationId1 = DB::table('notifications')->insertGetId([
            'type' => 'system',
            'title' => 'System maintenance completed',
            'message' => 'The scheduled maintenance window has finished and all services are back online.',
            'module' => 'global',
            'action_url' => '/module/super-admin/dashboard',
            'metadata' => json_encode([
                'severity' => 'info',
                'category' => 'system',
                'pinned' => true,
            ]),
            'created_by' => 'usr_001',
            'created_at' => $now,
        ]);

        DB::table('notification_targets')->insert([
            [
                'notification_id' => $notificationId1,
                'target_type' => 'all',
                'target_value' => null,
            ],
        ]);

        $allActiveUsers = DB::table('users')
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        DB::table('notification_recipients')->insert(array_map(static fn (string $userId) => [
            'notification_id' => $notificationId1,
            'user_id' => $userId,
            'read_at' => null,
            'dismissed_at' => null,
            'created_at' => $now,
        ], $allActiveUsers));

        $notificationId2 = DB::table('notifications')->insertGetId([
            'type' => 'info',
            'title' => 'English module schedule update',
            'message' => 'The English program team has published the updated weekly schedule.',
            'module' => 'english',
            'action_url' => '/module/english/dashboard',
            'metadata' => json_encode([
                'category' => 'schedule',
                'priority' => 'normal',
            ]),
            'created_by' => 'usr_046',
            'created_at' => $now,
        ]);

        DB::table('notification_targets')->insert([
            [
                'notification_id' => $notificationId2,
                'target_type' => 'module',
                'target_value' => 'english',
            ],
        ]);

        $englishUsers = DB::table('users')
            ->where('status', 'active')
            ->whereIn('role_code', ['adminenglish', 'teacher-english'])
            ->pluck('id')
            ->all();

        DB::table('notification_recipients')->insert(array_map(static fn (string $userId) => [
            'notification_id' => $notificationId2,
            'user_id' => $userId,
            'read_at' => null,
            'dismissed_at' => null,
            'created_at' => $now,
        ], $englishUsers));

        $notificationId3 = DB::table('notifications')->insertGetId([
            'type' => 'warning',
            'title' => 'Preschool attendance review required',
            'message' => 'Please review the latest attendance submissions before the end of the day.',
            'module' => 'preschool',
            'action_url' => '/module/preschool/attendance',
            'metadata' => json_encode([
                'category' => 'attendance',
                'actionRequired' => true,
            ]),
            'created_by' => 'usr_016',
            'created_at' => $now,
        ]);

        DB::table('notification_targets')->insert([
            [
                'notification_id' => $notificationId3,
                'target_type' => 'role',
                'target_value' => 'teacher-preschool',
            ],
        ]);

        DB::table('notification_recipients')->insert([
            [
                'notification_id' => $notificationId3,
                'user_id' => 'usr_106',
                'read_at' => null,
                'dismissed_at' => null,
                'created_at' => $now,
            ],
        ]);

        $notificationId4 = DB::table('notifications')->insertGetId([
            'type' => 'success',
            'title' => 'Scholarship application review completed',
            'message' => 'Your scholarship application batch has been reviewed and approved.',
            'module' => 'scholarship',
            'action_url' => '/module/scholarship/applications',
            'metadata' => json_encode([
                'category' => 'application',
                'result' => 'approved',
            ]),
            'created_by' => 'usr_031',
            'created_at' => $now,
        ]);

        DB::table('notification_targets')->insert([
            [
                'notification_id' => $notificationId4,
                'target_type' => 'user',
                'target_value' => 'usr_121',
            ],
        ]);

        DB::table('notification_recipients')->insert([
            [
                'notification_id' => $notificationId4,
                'user_id' => 'usr_121',
                'read_at' => $now,
                'dismissed_at' => null,
                'created_at' => $now,
            ],
        ]);

        $notificationId5 = DB::table('notifications')->insertGetId([
            'type' => 'error',
            'title' => 'Sports roster sync failed',
            'message' => 'The latest roster sync encountered a validation issue and needs another attempt.',
            'module' => 'sport',
            'action_url' => '/module/sport/teams',
            'metadata' => json_encode([
                'category' => 'sync',
                'retryable' => true,
            ]),
            'created_by' => 'usr_061',
            'created_at' => $now,
        ]);

        DB::table('notification_targets')->insert([
            [
                'notification_id' => $notificationId5,
                'target_type' => 'department',
                'target_value' => 'sports',
            ],
        ]);

        $sportUsers = DB::table('users')
            ->where('status', 'active')
            ->where('department_code', 'sports')
            ->pluck('id')
            ->all();

        DB::table('notification_recipients')->insert(array_map(static fn (string $userId) => [
            'notification_id' => $notificationId5,
            'user_id' => $userId,
            'read_at' => null,
            'dismissed_at' => null,
            'created_at' => $now,
        ], $sportUsers));
    }
}
