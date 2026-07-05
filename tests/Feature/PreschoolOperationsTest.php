<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowInstance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_dashboard_returns_normalized_payload_for_admins(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seedOperationsAuthReferences();

        $admin = User::factory()->create([
            'role_code' => 'adminpreschool',
            'department_code' => 'education',
            'must_change_password' => false,
        ]);

        $definition = PreschoolWorkflowDefinition::query()->where('key', 'invoice_collection')->firstOrFail();
        $step = $definition->steps()->orderBy('sort_order')->first();
        PreschoolWorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id,
            'source_type' => 'invoice',
            'source_id' => 'INV-OPS-001',
            'source_label' => 'Invoice INV-OPS-001',
            'current_step_id' => $step?->id,
            'status' => 'open',
            'priority' => 'high',
            'metadata' => [],
        ]);
        PreschoolWorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id,
            'source_type' => 'invoice',
            'source_id' => 'INV-OPS-002',
            'source_label' => 'Invoice INV-OPS-002',
            'current_step_id' => $step?->id,
            'status' => 'pending_approval',
            'priority' => 'normal',
            'metadata' => [],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/preschool/operations/dashboard')
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
                    'workflows' => ['summary', 'items', 'recentActivity'],
                    'timeline',
                    'quickActions',
                    'generatedAt',
                ],
            ])
            ->assertJsonFragment([
                'routeName' => 'dashboard-preschool-admin-attendance-history',
            ])
            ;

        $response->assertJsonPath('data.workflows.summary.pendingWorkflows', 2)
            ->assertJsonPath('data.workflows.summary.pendingApprovals', 1)
            ->assertJsonPath('data.workflows.recentActivity.0.sourceLabel', 'Invoice INV-OPS-002')
            ->assertJsonPath('data.workflows.items.0.sourceRouteName', 'dashboard-preschool-admin-invoice-detail');
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

