<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_dashboard_returns_normalized_payload_for_admins(): void
    {
        $this->seedOperationsAuthReferences();

        $admin = User::factory()->create([
            'role_code' => 'adminpreschool',
            'department_code' => 'education',
            'must_change_password' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/operations/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'operations')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'scope',
                    'summary',
                    'today',
                    'attendance' => ['summary', 'trends', 'breakdowns', 'charts', 'datasets'],
                    'sessions' => ['summary', 'trends', 'breakdowns', 'charts', 'datasets'],
                    'alerts' => ['summary', 'trends', 'breakdowns', 'charts', 'datasets'],
                    'guardianCommunications' => ['summary', 'trends', 'breakdowns', 'charts', 'datasets', 'items'],
                    'health',
                    'payments',
                    'assessments',
                    'teachers',
                    'students',
                    'risks',
                    'timeline',
                    'quickActions',
                    'generatedAt',
                ],
            ])
            ->assertJsonFragment([
                'routeName' => 'dashboard-preschool-admin-attendance-history',
            ]);
    }

    public function test_operations_dashboard_allows_teacher_preschool_access(): void
    {
        $this->seedOperationsAuthReferences();

        $teacher = User::factory()->create([
            'role_code' => 'teacher-preschool',
            'department_code' => 'education',
            'must_change_password' => false,
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/operations/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'operations');
    }

    public function test_operations_dashboard_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/preschool/operations/dashboard')
            ->assertUnauthorized();
    }

    private function seedOperationsAuthReferences(): void
    {
        Department::query()->updateOrCreate(
            ['code' => 'operations'],
            ['name' => 'Operations', 'display_order' => 1, 'is_active' => true]
        );

        Department::query()->updateOrCreate(
            ['code' => 'education'],
            ['name' => 'Education', 'display_order' => 2, 'is_active' => true]
        );

        Role::query()->updateOrCreate(
            ['code' => 'adminpreschool'],
            [
                'name' => 'Preschool Admin',
                'scope' => 'admin',
                'domain_code' => 'preschool',
                'department_code' => 'education',
                'sort_order' => 3,
            ]
        );

        Role::query()->updateOrCreate(
            ['code' => 'teacher-preschool'],
            [
                'name' => 'Preschool Teacher',
                'scope' => 'staff',
                'domain_code' => 'preschool',
                'department_code' => 'education',
                'sort_order' => 7,
            ]
        );
    }
}

