<?php

namespace Tests\Unit\Http\Resources\Auth;

use App\Http\Resources\Auth\UserResource;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_resource_returns_r2_avatar_url_for_stored_avatar_path(): void
    {
        Storage::fake('r2');

        $originalImageDisk = config('filesystems.image_disk');
        $originalR2Url = config('filesystems.disks.r2.url');

        config([
            'filesystems.image_disk' => 'r2',
            'filesystems.disks.r2.url' => 'https://pub-123abc.r2.dev',
        ]);

        try {
            $avatarPath = 'avatars/profile.jpg';
            Storage::disk('r2')->put($avatarPath, 'avatar-bytes');

            $user = $this->createUser([
                'avatar' => $avatarPath,
            ]);
            $user->loadMissing(['department', 'role', 'permissions']);

            $payload = UserResource::make($user)->resolve(new Request);

            $this->assertSame(Storage::disk('r2')->url($avatarPath), $payload['avatar']);
        } finally {
            config([
                'filesystems.image_disk' => $originalImageDisk,
                'filesystems.disks.r2.url' => $originalR2Url,
            ]);
        }
    }

    private function createUser(array $overrides = []): User
    {
        $role = Role::query()->findOrFail('superadmin');

        $user = User::query()->create(array_merge([
            'id' => 'usr-unit-avatar',
            'first_name' => 'Test',
            'last_name' => 'Avatar',
            'username' => 'Test Avatar',
            'email' => 'test.avatar@hfccf.org',
            'phone' => '+855 12 999 999',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'avatar' => null,
            'password' => 'secret-pass',
        ], $overrides));

        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission_code' => 'all:*',
        ]);

        return $user;
    }
}
