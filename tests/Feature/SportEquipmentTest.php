<?php

namespace Tests\Feature;

use App\Models\SportEquipmentItem;
use App\Models\SportEquipmentRequest;
use App\Models\SportTeam;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SportEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminsport_can_manage_equipment_and_dashboard_low_stock_summary(): void
    {
        Sanctum::actingAs($this->createUser('adminsport', 'equip-admin-1', 'equip-admin-1@hfccf.org'));

        $lowStockCreate = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-LOW-001',
            'name' => 'Low Stock Cones',
            'category' => 'Training',
            'description' => 'Low stock test item.',
            'unit' => 'set',
            'total_quantity' => 3,
            'available_quantity' => 1,
            'minimum_stock_level' => 2,
            'storage_location' => 'Locker A',
            'status' => 'active',
        ]);

        $lowStockCreate
            ->assertCreated()
            ->assertJsonPath('data.item.equipmentCode', 'EQ-LOW-001')
            ->assertJsonPath('data.item.isLowStock', true);

        $lowStockItemId = $lowStockCreate->json('data.item.id');

        $activeCreate = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-ACTIVE-001',
            'name' => 'Match Balls',
            'category' => 'Ball',
            'unit' => 'pc',
            'total_quantity' => 5,
            'available_quantity' => 5,
            'minimum_stock_level' => 2,
            'status' => 'active',
        ]);

        $activeCreate
            ->assertCreated()
            ->assertJsonPath('data.item.equipmentCode', 'EQ-ACTIVE-001');

        $activeItemId = $activeCreate->json('data.item.id');

        $dashboard = $this->getJson('/api/sport/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.lowStockItems', 1);

        $list = $this->getJson('/api/sport/admin/equipment?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $list->json('data.items'));

        $this->getJson('/api/sport/admin/equipment/'.$lowStockItemId)
            ->assertOk()
            ->assertJsonPath('data.item.equipmentCode', 'EQ-LOW-001');

        $this->putJson('/api/sport/admin/equipment/'.$activeItemId, [
            'name' => 'Match Balls Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.item.name', 'Match Balls Updated');

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $activeItemId,
            'name' => 'Match Balls Updated',
            'status' => 'active',
        ]);
    }

    public function test_coach_requests_are_scoped_to_assigned_teams_and_cannot_spoof_scope(): void
    {
        $admin = $this->createUser('adminsport', 'equip-admin-2', 'equip-admin-2@hfccf.org');
        $coach = $this->createUser('coach', 'equip-coach-1', 'equip-coach-1@hfccf.org');
        $otherCoach = $this->createUser('coach', 'equip-coach-2', 'equip-coach-2@hfccf.org');
        $assignedTeam = $this->createTeam('EQUIP-TEAM-1', 'Assigned FC');
        $otherTeam = $this->createTeam('EQUIP-TEAM-2', 'Foreign FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $assignedTeam->id,
            'status' => 'active',
        ])->assertCreated();

        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $otherCoach->id,
            'team_id' => $otherTeam->id,
            'status' => 'active',
        ])->assertCreated();

        $requestableItem = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-REQ-001',
            'name' => 'Requestable Cones',
            'category' => 'Training',
            'unit' => 'set',
            'total_quantity' => 5,
            'available_quantity' => 5,
            'minimum_stock_level' => 2,
            'status' => 'active',
        ])->assertCreated()->json('data.item');

        Sanctum::actingAs($coach);

        $equipment = $this->getJson('/api/sport/coach/equipment')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.totalActiveItems', 1);

        $this->postJson('/api/sport/coach/equipment/requests', [
            'equipment_item_id' => $requestableItem['id'],
            'team_id' => $otherTeam->id,
            'requested_quantity' => 1,
            'purpose' => 'Foreign team attempt',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-16',
        ])->assertForbidden();

        $created = $this->postJson('/api/sport/coach/equipment/requests', [
            'coach_user_id' => $otherCoach->id,
            'equipment_item_id' => $requestableItem['id'],
            'team_id' => $assignedTeam->id,
            'requested_quantity' => 2,
            'purpose' => 'Training session',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-17',
        ])
            ->assertCreated()
            ->assertJsonPath('data.request.status', 'pending')
            ->assertJsonPath('data.request.coachUserId', $coach->id)
            ->assertJsonPath('data.request.teamId', $assignedTeam->id);

        $requestId = $created->json('data.request.id');

        $coachRequests = $this->getJson('/api/sport/coach/equipment/requests')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $coachRequests->json('data.items'));
        $this->assertSame($requestId, $coachRequests->json('data.items.0.id'));

        Sanctum::actingAs($otherCoach);
        $otherCoachRequests = $this->getJson('/api/sport/coach/equipment/requests')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(0, $otherCoachRequests->json('data.items'));

        Sanctum::actingAs($coach);
        $this->getJson('/api/sport/admin/equipment')->assertForbidden();
    }

    public function test_request_lifecycle_updates_stock_transactionally_and_blocks_invalid_transitions(): void
    {
        $admin = $this->createUser('adminsport', 'equip-admin-3', 'equip-admin-3@hfccf.org');
        $coach = $this->createUser('coach', 'equip-coach-3', 'equip-coach-3@hfccf.org');
        $team = $this->createTeam('EQUIP-TEAM-3', 'Lifecycle FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        $item = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-LIFECYCLE-001',
            'name' => 'Lifecycle Balls',
            'category' => 'Ball',
            'unit' => 'pc',
            'total_quantity' => 5,
            'available_quantity' => 5,
            'minimum_stock_level' => 2,
            'status' => 'active',
        ])->assertCreated()->json('data.item');

        Sanctum::actingAs($coach);
        $request = $this->postJson('/api/sport/coach/equipment/requests', [
            'equipment_item_id' => $item['id'],
            'team_id' => $team->id,
            'requested_quantity' => 2,
            'purpose' => 'Daily training',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-18',
        ])->assertCreated()->json('data.request');

        Sanctum::actingAs($admin);

        $approved = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 2,
            'admin_note' => 'Approved for training',
        ])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'approved')
            ->assertJsonPath('data.request.approvedQuantity', 2);

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $item['id'],
            'available_quantity' => 5,
            'total_quantity' => 5,
        ]);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 3,
        ])->assertUnprocessable();

        $issued = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 2,
            'admin_note' => 'Issued from stock',
        ])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'issued')
            ->assertJsonPath('data.request.issuedQuantity', 2);

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $item['id'],
            'available_quantity' => 3,
            'total_quantity' => 5,
        ]);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 1,
        ])->assertUnprocessable();

        $returned = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/return', [
            'returned_quantity' => 1,
            'damaged_quantity' => 1,
            'missing_quantity' => 0,
            'admin_note' => 'Balanced return',
        ])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'returned')
            ->assertJsonPath('data.request.returnedQuantity', 1)
            ->assertJsonPath('data.request.damagedQuantity', 1)
            ->assertJsonPath('data.request.missingQuantity', 0);

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $item['id'],
            'available_quantity' => 4,
            'total_quantity' => 4,
        ]);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 1,
        ])->assertUnprocessable();

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/return', [
            'returned_quantity' => 1,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('sport_equipment_requests', [
            'id' => $request['id'],
            'status' => 'returned',
            'approved_quantity' => 2,
            'issued_quantity' => 2,
            'returned_quantity' => 1,
            'damaged_quantity' => 1,
            'missing_quantity' => 0,
        ]);
    }

    public function test_return_arithmetic_matches_stock_convention_and_issue_fails_when_stock_changes_after_approval(): void
    {
        $admin = $this->createUser('adminsport', 'equip-admin-4', 'equip-admin-4@hfccf.org');
        $coach = $this->createUser('coach', 'equip-coach-4', 'equip-coach-4@hfccf.org');
        $team = $this->createTeam('EQUIP-TEAM-4', 'Arithmetic FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        $approvalItem = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-APPROVAL-001',
            'name' => 'Approval Bibs',
            'category' => 'Training',
            'unit' => 'set',
            'total_quantity' => 10,
            'available_quantity' => 10,
            'minimum_stock_level' => 2,
            'status' => 'active',
        ])->assertCreated()->json('data.item');

        Sanctum::actingAs($coach);
        $approvalRequest = $this->postJson('/api/sport/coach/equipment/requests', [
            'equipment_item_id' => $approvalItem['id'],
            'team_id' => $team->id,
            'requested_quantity' => 5,
            'purpose' => 'Approval check',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-17',
        ])->assertCreated()->json('data.request');

        Sanctum::actingAs($admin);
        $this->patchJson('/api/sport/admin/equipment-requests/'.$approvalRequest['id'].'/approve', [
            'approved_quantity' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $approvalItem['id'],
            'total_quantity' => 10,
            'available_quantity' => 10,
        ]);

        SportEquipmentItem::query()
            ->whereKey($approvalItem['id'])
            ->update(['available_quantity' => 4]);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$approvalRequest['id'].'/issue', [
            'issued_quantity' => 5,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $approvalItem['id'],
            'total_quantity' => 10,
            'available_quantity' => 4,
        ]);

        $item = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-ARITH-001',
            'name' => 'Arithmetic Bibs',
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
            'purpose' => 'Session gear',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-17',
        ])->assertCreated()->json('data.request');

        Sanctum::actingAs($admin);
        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $item['id'],
            'total_quantity' => 10,
            'available_quantity' => 10,
        ]);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/issue', [
            'issued_quantity' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $item['id'],
            'total_quantity' => 10,
            'available_quantity' => 5,
        ]);

        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/return', [
            'returned_quantity' => 2,
            'damaged_quantity' => 2,
            'missing_quantity' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('sport_equipment_items', [
            'id' => $item['id'],
            'total_quantity' => 7,
            'available_quantity' => 7,
        ]);

        $approvedAfterReturn = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 5,
        ]);

        $approvedAfterReturn->assertUnprocessable();
    }

    public function test_admin_approval_requires_a_positive_approved_quantity(): void
    {
        $admin = $this->createUser('adminsport', 'equip-admin-5', 'equip-admin-5@hfccf.org');
        $coach = $this->createUser('coach', 'equip-coach-5', 'equip-coach-5@hfccf.org');
        $team = $this->createTeam('EQUIP-TEAM-5', 'Validation FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        $item = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-VALIDATION-001',
            'name' => 'Validation Bibs',
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
            'requested_quantity' => 4,
            'purpose' => 'Validation check',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-18',
        ])->assertCreated()->json('data.request');

        Sanctum::actingAs($admin);

        $missingQuantity = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', []);
        $missingQuantity
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The approved quantity field is required.')
            ->assertJsonPath('data.errors.approved_quantity.0', 'The approved quantity field is required.');

        $zeroQuantity = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 0,
        ]);
        $zeroQuantity
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The approved quantity field must be at least 1.')
            ->assertJsonPath('data.errors.approved_quantity.0', 'The approved quantity field must be at least 1.');

        $aboveRequested = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 5,
        ]);
        $aboveRequested
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Approved quantity cannot exceed requested quantity.')
            ->assertJsonPath('data', null);
    }

    public function test_non_pending_request_cannot_be_approved(): void
    {
        $admin = $this->createUser('adminsport', 'equip-admin-6', 'equip-admin-6@hfccf.org');
        $coach = $this->createUser('coach', 'equip-coach-6', 'equip-coach-6@hfccf.org');
        $team = $this->createTeam('EQUIP-TEAM-6', 'Lifecycle Guard FC');

        Sanctum::actingAs($admin);
        $this->postJson('/api/sport/admin/coach-team-assignments', [
            'coach_user_id' => $coach->id,
            'team_id' => $team->id,
            'status' => 'active',
        ])->assertCreated();

        $item = $this->postJson('/api/sport/admin/equipment', [
            'equipment_code' => 'EQ-GUARD-001',
            'name' => 'Lifecycle Guard Bibs',
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
            'requested_quantity' => 3,
            'purpose' => 'Lifecycle guard',
            'required_date' => '2026-07-15',
            'expected_return_date' => '2026-07-18',
        ])->assertCreated()->json('data.request');

        Sanctum::actingAs($admin);
        $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 3,
        ])->assertOk();

        $pendingApprove = $this->patchJson('/api/sport/admin/equipment-requests/'.$request['id'].'/approve', [
            'approved_quantity' => 3,
        ]);

        $pendingApprove
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Equipment request is not pending.')
            ->assertJsonPath('data', null);
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
