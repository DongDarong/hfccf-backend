<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolClassLevelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminpreschool_can_list_create_update_deactivate_and_restore_class_levels(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cl_500', 'preschool.classlevel500@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/class-levels')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'code' => 'NUR',
            ]);

        $create = $this->postJson('/api/preschool/class-levels', [
            'name_en' => 'Senior Nursery',
            'name_kh' => 'ជាន់ខ្ពស់ Nursery',
            'code' => 'SNR',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.classLevel.code', 'SNR');

        $classLevelId = $create->json('data.classLevel.id');

        $this->assertDatabaseHas('preschool_class_levels', [
            'id' => $classLevelId,
            'name_en' => 'Senior Nursery',
            'code' => 'SNR',
            'is_active' => 1,
        ]);

        $this->putJson('/api/preschool/class-levels/'.$classLevelId, [
            'name_en' => 'Senior Nursery Updated',
            'code' => 'SNR2',
            'sort_order' => 6,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.classLevel.code', 'SNR2');

        $this->patchJson('/api/preschool/class-levels/'.$classLevelId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.classLevel.isActive', false);

        $this->assertDatabaseHas('preschool_class_levels', [
            'id' => $classLevelId,
            'is_active' => 0,
        ]);

        $this->patchJson('/api/preschool/class-levels/'.$classLevelId.'/restore')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.classLevel.isActive', true);

        $this->assertDatabaseHas('preschool_class_levels', [
            'id' => $classLevelId,
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_class_level_code_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cl_501', 'preschool.classlevel501@hfccf.org');
        Sanctum::actingAs($admin);

        $this->postJson('/api/preschool/class-levels', [
            'name_en' => 'Duplicate Nursery',
            'code' => 'NUR',
            'sort_order' => 9,
            'is_active' => true,
        ])->assertStatus(422);

        $this->assertDatabaseCount('preschool_class_levels', 4);
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => ucfirst(str_replace('-', ' ', $roleCode)).' User',
            'email' => $email,
            'phone' => '+855 12 555 555',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        $this->syncPermissions($user, $role);

        return $user;
    }

    private function syncPermissions(User $user, Role $role): void
    {
        $rows = $role->permissions->map(static fn ($permission) => [
            'user_id' => $user->id,
            'permission_code' => $permission->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }
    }
}
