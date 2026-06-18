<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HfccfAuthSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('departments')->upsert([
            ['code' => 'operations', 'name' => 'Operations', 'display_order' => 1, 'is_active' => true],
            ['code' => 'education', 'name' => 'Education', 'display_order' => 2, 'is_active' => true],
            ['code' => 'sports', 'name' => 'Sports', 'display_order' => 3, 'is_active' => true],
            ['code' => 'administration', 'name' => 'Administration', 'display_order' => 4, 'is_active' => true],
        ], ['code'], ['name', 'display_order', 'is_active']);

        DB::table('roles')->upsert([
            ['code' => 'superadmin', 'name' => 'Super Admin', 'scope' => 'super_admin', 'domain_code' => 'global', 'department_code' => 'operations', 'sort_order' => 1],
            ['code' => 'adminenglish', 'name' => 'English Admin', 'scope' => 'admin', 'domain_code' => 'english', 'department_code' => 'education', 'sort_order' => 2],
            ['code' => 'adminpreschool', 'name' => 'Preschool Admin', 'scope' => 'admin', 'domain_code' => 'preschool', 'department_code' => 'education', 'sort_order' => 3],
            ['code' => 'adminscholarship', 'name' => 'Scholarship Admin', 'scope' => 'admin', 'domain_code' => 'scholarship', 'department_code' => 'education', 'sort_order' => 4],
            ['code' => 'adminsport', 'name' => 'Sport Admin', 'scope' => 'admin', 'domain_code' => 'sport', 'department_code' => 'sports', 'sort_order' => 5],
            ['code' => 'teacher-english', 'name' => 'English Teacher', 'scope' => 'staff', 'domain_code' => 'english', 'department_code' => 'education', 'sort_order' => 6],
            ['code' => 'teacher-preschool', 'name' => 'Preschool Teacher', 'scope' => 'staff', 'domain_code' => 'preschool', 'department_code' => 'education', 'sort_order' => 7],
            ['code' => 'teacher-scholarship', 'name' => 'Scholarship Teacher', 'scope' => 'staff', 'domain_code' => 'scholarship', 'department_code' => 'education', 'sort_order' => 8],
            ['code' => 'coach', 'name' => 'Coach', 'scope' => 'staff', 'domain_code' => 'sport', 'department_code' => 'sports', 'sort_order' => 9],
            // Legacy compatibility only: guardian remains a portal-scoped role
            // for historical data, but Preschool architecture should treat the
            // guardian record itself as data-only rather than a normal user.
            ['code' => 'guardian', 'name' => 'Guardian', 'scope' => 'portal', 'domain_code' => 'preschool', 'department_code' => 'education', 'sort_order' => 10],
        ], ['code'], ['name', 'scope', 'domain_code', 'department_code', 'sort_order']);

        DB::table('permissions')->upsert([
            ['code' => 'all:*', 'module' => 'all', 'name' => 'Full system access'],
            ['code' => 'athletes:read', 'module' => 'athletes', 'name' => 'Read athletes'],
            ['code' => 'attendance:write', 'module' => 'attendance', 'name' => 'Manage attendance'],
            ['code' => 'classes:write', 'module' => 'classes', 'name' => 'Manage classes'],
            ['code' => 'dashboard:read', 'module' => 'dashboard', 'name' => 'Read dashboard'],
            ['code' => 'programs:write', 'module' => 'programs', 'name' => 'Manage programs'],
            ['code' => 'reports:read', 'module' => 'reports', 'name' => 'Read reports'],
            ['code' => 'settings:read', 'module' => 'settings', 'name' => 'Read settings'],
            ['code' => 'students:read', 'module' => 'students', 'name' => 'Read students'],
            ['code' => 'students:write', 'module' => 'students', 'name' => 'Manage students'],
            ['code' => 'tasks:read', 'module' => 'tasks', 'name' => 'Read tasks'],
            ['code' => 'tasks:write', 'module' => 'tasks', 'name' => 'Manage tasks'],
            ['code' => 'training:write', 'module' => 'training', 'name' => 'Manage training'],
            ['code' => 'users:read', 'module' => 'users', 'name' => 'Read users'],
            ['code' => 'users:reset', 'module' => 'users', 'name' => 'Reset user passwords'],
            ['code' => 'users:write', 'module' => 'users', 'name' => 'Manage users'],
        ], ['code'], ['module', 'name']);

        DB::table('role_permissions')->insertOrIgnore([
            ['role_code' => 'superadmin', 'permission_code' => 'all:*'],

            ['role_code' => 'adminenglish', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'adminenglish', 'permission_code' => 'users:read'],
            ['role_code' => 'adminenglish', 'permission_code' => 'users:reset'],
            ['role_code' => 'adminenglish', 'permission_code' => 'reports:read'],
            ['role_code' => 'adminenglish', 'permission_code' => 'programs:write'],

            ['role_code' => 'adminpreschool', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'users:read'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'users:reset'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'users:write'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'reports:read'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'classes:write'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'students:read'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'students:write'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'attendance:write'],
            ['role_code' => 'adminpreschool', 'permission_code' => 'settings:read'],

            ['role_code' => 'adminscholarship', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'adminscholarship', 'permission_code' => 'users:read'],
            ['role_code' => 'adminscholarship', 'permission_code' => 'users:reset'],
            ['role_code' => 'adminscholarship', 'permission_code' => 'users:write'],
            ['role_code' => 'adminscholarship', 'permission_code' => 'reports:read'],
            ['role_code' => 'adminscholarship', 'permission_code' => 'settings:read'],

            ['role_code' => 'adminsport', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'adminsport', 'permission_code' => 'users:read'],
            ['role_code' => 'adminsport', 'permission_code' => 'users:reset'],
            ['role_code' => 'adminsport', 'permission_code' => 'reports:read'],
            ['role_code' => 'adminsport', 'permission_code' => 'programs:write'],

            ['role_code' => 'coach', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'coach', 'permission_code' => 'athletes:read'],
            ['role_code' => 'coach', 'permission_code' => 'training:write'],

            ['role_code' => 'teacher-english', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'teacher-english', 'permission_code' => 'tasks:read'],
            ['role_code' => 'teacher-english', 'permission_code' => 'tasks:write'],

            ['role_code' => 'teacher-preschool', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'teacher-preschool', 'permission_code' => 'classes:write'],
            ['role_code' => 'teacher-preschool', 'permission_code' => 'students:read'],
            ['role_code' => 'teacher-preschool', 'permission_code' => 'attendance:write'],
            ['role_code' => 'teacher-preschool', 'permission_code' => 'tasks:read'],
            ['role_code' => 'teacher-preschool', 'permission_code' => 'tasks:write'],

            ['role_code' => 'teacher-scholarship', 'permission_code' => 'dashboard:read'],
            ['role_code' => 'teacher-scholarship', 'permission_code' => 'tasks:read'],
            ['role_code' => 'teacher-scholarship', 'permission_code' => 'tasks:write'],
        ]);

        DB::table('users')->upsert([ 
            [
                'id' => 'usr_001',
                'first_name' => 'Vanna',
                'last_name' => 'Nop',
                'username' => 'Vanna Nop',
                'email' => 'superadmin01@hfccf.org',
                'phone' => '+855 12 301 001',
                'role_code' => 'superadmin',
                'department_code' => 'operations',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$VDxiTyF8hTrUu9rcWfcAveVDiHxBP5NdngXzfVCalxnrOt8oqlLOG',
                'last_login_at' => '2026-03-04 04:15:00',
                'created_at' => '2026-02-01 08:00:00',
                'updated_at' => '2026-02-01 08:00:00',
            ],
            [
                'id' => 'usr_002',
                'first_name' => 'Darong',
                'last_name' => 'Admin',
                'username' => 'dngdarong',
                'email' => 'dngdarong@gmail.com',
                'phone' => null,
                'role_code' => 'superadmin',
                'department_code' => 'operations',
                'status' => 'active',
                'avatar' => null,
                'password' => Hash::make('Darong@123'),
                'last_login_at' => null,
                'created_at' => '2026-05-12 12:00:00',
                'updated_at' => '2026-05-12 12:00:00',
            ],
            [
                'id' => 'usr_016',
                'first_name' => 'Sovann',
                'last_name' => 'Lim',
                'username' => 'Sovann Lim',
                'email' => 'preschool.admin01@hfccf.org',
                'phone' => '+855 12 316 016',
                'role_code' => 'adminpreschool',
                'department_code' => 'education',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$DL4pVR9wQwhWbiB.UqWUteKxr.mA0nELKCblW1LWNGTxV6w.fLKaG',
                'last_login_at' => '2026-03-03 04:15:00',
                'created_at' => '2026-02-16 08:00:00',
                'updated_at' => '2026-02-16 08:00:00',
            ],
            [
                'id' => 'usr_031',
                'first_name' => 'Sokun',
                'last_name' => 'Nop',
                'username' => 'Sokun Nop',
                'email' => 'scholarship.admin01@hfccf.org',
                'phone' => '+855 12 331 031',
                'role_code' => 'adminscholarship',
                'department_code' => 'education',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$JFyQX2264zF7398Hhf7.zOXyVQutg2T/pMZ0yJ5KZTp2c.NMNpI9a',
                'last_login_at' => '2026-03-02 04:15:00',
                'created_at' => '2026-02-04 08:00:00',
                'updated_at' => '2026-02-04 08:00:00',
            ],
            [
                'id' => 'usr_046',
                'first_name' => 'Sophea',
                'last_name' => 'Lim',
                'username' => 'Sophea Lim',
                'email' => 'english.admin01@hfccf.org',
                'phone' => '+855 12 346 046',
                'role_code' => 'adminenglish',
                'department_code' => 'education',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$FPHRt1Qo.Bqdz66ebsxok.HowfnX23ivq95NXFFltbQRINlnmsH2O',
                'last_login_at' => '2026-03-01 04:15:00',
                'created_at' => '2026-02-19 08:00:00',
                'updated_at' => '2026-02-19 08:00:00',
            ],
            [
                'id' => 'usr_061',
                'first_name' => 'Pisey',
                'last_name' => 'Nop',
                'username' => 'Pisey Nop',
                'email' => 'sport.admin01@hfccf.org',
                'phone' => '+855 12 361 061',
                'role_code' => 'adminsport',
                'department_code' => 'sports',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$.ABQclzC/hcDA76xiptxeuNmMQBppVFkhRJXnLo2Mr9JFTBG0/s2a',
                'last_login_at' => '2026-03-08 04:15:00',
                'created_at' => '2026-02-07 08:00:00',
                'updated_at' => '2026-02-07 08:00:00',
            ],
            [
                'id' => 'usr_076',
                'first_name' => 'Rina',
                'last_name' => 'Lim',
                'username' => 'Rina Lim',
                'email' => 'coach01@hfccf.org',
                'phone' => '+855 12 376 076',
                'role_code' => 'coach',
                'department_code' => 'sports',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$0xolL2LnIuMQXe7W0l1.nu4vjDUgK1X38Ne./LJhk0tFxXrnzZ8fK',
                'last_login_at' => '2026-03-07 04:15:00',
                'created_at' => '2026-02-22 08:00:00',
                'updated_at' => '2026-02-22 08:00:00',
            ],
            [
                'id' => 'usr_091',
                'first_name' => 'Sreypov',
                'last_name' => 'Nop',
                'username' => 'Sreypov Nop',
                'email' => 'teacher.english01@hfccf.org',
                'phone' => '+855 12 391 091',
                'role_code' => 'teacher-english',
                'department_code' => 'education',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$ZhQvc5g8xmR798XPkHtX.OJAFPU20VZqVjqwmu1KMCZWrfGY416hy',
                'last_login_at' => '2026-03-06 04:15:00',
                'created_at' => '2026-02-10 08:00:00',
                'updated_at' => '2026-02-10 08:00:00',
            ],
            [
                'id' => 'usr_106',
                'first_name' => 'Vannak',
                'last_name' => 'Lim',
                'username' => 'Vannak Lim',
                'email' => 'teacher.preschool01@hfccf.org',
                'phone' => '+855 12 406 106',
                'role_code' => 'teacher-preschool',
                'department_code' => 'education',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$8Yhy10mQzt0AS9hkzIrF4.wvhAr3spix6cJqfUTzd89RmBC.GRAXe',
                'last_login_at' => '2026-03-05 04:15:00',
                'created_at' => '2026-02-25 08:00:00',
                'updated_at' => '2026-02-25 08:00:00',
            ],
            [
                'id' => 'usr_121',
                'first_name' => 'Nita',
                'last_name' => 'Nop',
                'username' => 'Nita Nop',
                'email' => 'teacher.scholarship01@hfccf.org',
                'phone' => '+855 12 421 121',
                'role_code' => 'teacher-scholarship',
                'department_code' => 'education',
                'status' => 'active',
                'avatar' => 'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
                'password' => '$2y$10$XVIt6GQJliG6e6phQumQeeD0gbWdEzH/fEUSeetq.8Ttj6Gxj1BWG',
                'last_login_at' => '2026-03-04 04:15:00',
                'created_at' => '2026-02-13 08:00:00',
                'updated_at' => '2026-02-13 08:00:00',
            ],
        ], ['id'], ['first_name', 'last_name', 'username', 'email', 'phone', 'role_code', 'department_code', 'status', 'avatar', 'password', 'last_login_at', 'created_at', 'updated_at']);

        $permissionRows = DB::table('users as u')
            ->join('role_permissions as rp', 'rp.role_code', '=', 'u.role_code')
            ->selectRaw('u.id as user_id, rp.permission_code')
            ->get()
            ->map(static fn ($row): array => [
                'user_id' => $row->user_id,
                'permission_code' => $row->permission_code,
            ])
            ->all();

        DB::table('user_permissions')->insertOrIgnore($permissionRows);
    }
}
