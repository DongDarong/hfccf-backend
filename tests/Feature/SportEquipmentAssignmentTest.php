<?php

namespace Tests\Feature;

use App\Models\SportEquipmentAssignment;
use App\Models\SportTeam;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportEquipmentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_issue_creates_assignment_and_duplicate_issue_is_rejected(): void
    {
        [$admin, $coach, $team, $item, $request] = $this->prepareApprovedRequest('assignment-1');

        Sanctum::actingAs($admin);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'issued');

        $this->assertDatabaseHas('sport_equipment_assignments', [
            'equipment_request_id' => $request['id'],
            'equipment_item_id' => $item['id'],
            'team_id' => $team->id,
            'coach_user_id' => $coach->id,
            'assigned_quantity' => 2,
            'status' => 'assigned',
        ]);

        $list = $this->getJson('/api/sport/admin/equipment-assignments?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $assignmentId = $list->json('data.items.0.id');
        $this->getJson('/api/sport/admin/equipment-assignments/'.$assignmentId)
            ->assertOk()
            ->assertJsonPath('data.assignment.assignedQuantity', 2);

        $this->getJson('/api/sport/admin/equipment/'.$item['id'].'/assignments')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->getJson('/api/sport/admin/teams/'.$team->id.'/equipment-assignments')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 1,
        ])->assertUnprocessable();

        $this->assertSame(1, SportEquipmentAssignment::query()->count());
    }

    public function test_return_completes_assignment_and_preserves_inventory_arithmetic(): void
    {
        [$admin, , , $item, $request] = $this->prepareApprovedRequest('assignment-2');

        Sanctum::actingAs($admin);
        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 5,
        ])->assertOk();

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/return', [
            'returned_quantity' => 2,
            'damaged_quantity' => 2,
            'missing_quantity' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('sport_equipment_assignments', [
            'equipment_request_id' => $request['id'],
            'assigned_quantity' => 5,
            'returned_quantity' => 2,
            'damaged_quantity' => 2,
            'missing_quantity' => 1,
            'status' => 'returned',
        ]);

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $item['id'],
            'total_quantity' => 7,
            'available_quantity' => 7,
        ]);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/return', [
            'returned_quantity' => 5,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
        ])->assertUnprocessable();
    }

    public function test_coach_can_only_view_assignments_for_assigned_teams(): void
    {
        [$admin, $coach, $team, , $request] = $this->prepareApprovedRequest('assignment-3');
        $otherCoach = $this->createUser('coach', 'assignment-other-coach', 'assignment-other-coach@hfccf.org');
        $otherTeam = $this->createTeam('ASSIGNMENT-OTHER', 'Other Assignment FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $otherCoach->id,
            'team_id' => $otherTeam->id,
            'status' => 'active',
        ])->assertCreated();

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 1,
        ])->assertOk();

        $otherRequest = $this->createApprovedRequest($admin, $otherCoach, $otherTeam, 'assignment-4');
        $this->patchJson('/api/sport/admin/equipment-requests/'.$otherRequest['id'].'/issue', [
            'issued_quantity' => 1,
        ])->assertOk();

        Sanctum::actingAs($coach);
        $list = $this->getJson('/api/sport/coach/equipment/assignments')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $ownAssignmentId = $list->json('data.items.0.id');
        $otherAssignmentId = SportEquipmentAssignment::query()
            ->where('team_id', $otherTeam->id)
            ->value('id');

        $this->getJson('/api/sport/coach/equipment/assignments/'.$ownAssignmentId)->assertOk();
        $this->getJson('/api/sport/coach/equipment/assignments/'.$otherAssignmentId)->assertForbidden();
        $this->getJson('/api/sport/admin/equipment-assignments')->assertForbidden();
    }

    public function test_non_sport_roles_cannot_view_assignments(): void
    {
        $english = $this->createUser('adminenglish', 'assignment-english', 'assignment-english@hfccf.org');

        Sanctum::actingAs($english);

        $this->getJson('/api/sport/admin/equipment-assignments')->assertForbidden();
        $this->getJson('/api/sport/coach/equipment/assignments')->assertForbidden();
    }

    private function prepareApprovedRequest(string $suffix): array
    {
        $admin = $this->createUser('adminsport', 'assignment-admin-'.$suffix, 'assignment-admin-'.$suffix.'@hfccf.org');
        $coach = $this->createUser('coach', 'assignment-coach-'.$suffix, 'assignment-coach-'.$suffix.'@hfccf.org');
        $team = $this->createTeam('ASSIGNMENT-'.strtoupper($suffix), 'Assignment '.strtoupper($suffix).' FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        $item = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-'.strtoupper($suffix),
            'name' => 'Assignment Equipment',
            'category' => 'Training',
            'unit' => 'set',
            'total_quantity' => 10,
            'available_quantity' => 10,
            'minimum_stock_level' => 2,
            'status' => 'active',
        ])->assertCreated()->json('data.item');

        Sanctum::actingAs($coach);
        $request = $this->postJson('/api/sport/coach/equipment/requests', [
            'equipment_item_id' => $item['id'],
            'team_id' => $team->id,
            'requested_quantity' => 5,
            'purpose' => 'Equipment assignment test',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-18',
        ])->assertCreated()->json('data.request');

        Sanctum::actingAs($admin);
        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 5,
        ])->assertOk();

        return [$admin, $coach, $team, $item, $request];
    }

    private function createApprovedRequest(User $admin, User $coach, SportTeam $team, string $suffix): array
    {
        $item = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-'.strtoupper($suffix),
            'name' => 'Other Assignment Equipment',
            'category' => 'Training',
            'unit' => 'set',
            'total_quantity' => 10,
            'available_quantity' => 10,
            'minimum_stock_level' => 2,
            'status' => 'active',
        ])->assertCreated()->json('data.item');

        Sanctum::actingAs($coach);
        $request = $this->postJson('/api/sport/coach/equipment/requests', [
            'equipment_item_id' => $item['id'],
            'team_id' => $team->id,
            'requested_quantity' => 1,
            'purpose' => 'Other assignment test',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-18',
        ])->assertCreated()->json('data.request');

        Sanctum::actingAs($admin);
        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 1,
        ])->assertOk();

        return $request;
    }

    private function createUser(string $roleCode, string $id, string $email): User
    {
        return User::query()->create([
            'id' => $id,
            'first_name' => ucfirst($roleCode),
            'last_name' => 'User',
            'username' => ucfirst($roleCode).' '.$id,
            'email' => $email,
            'phone' => '+855 12 800 800',
            'role_code' => $roleCode,
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);
    }

    private function createTeam(string $code, string $name): SportTeam
    {
        return SportTeam::query()->create([
            'team_code' => $code,
            'name' => $name,
            'status' => 'active',
        ]);
    }
}
