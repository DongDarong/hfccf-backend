<?php

namespace Tests\Feature;

use App\Models\PreschoolClassroomResource;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolClassroomResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_preschool_can_list_classroom_resources(): void
    {
        $teacher = $this->createUserWithRole('teacher-preschool');
        Sanctum::actingAs($teacher);

        $resource = PreschoolClassroomResource::query()->create([
            'name' => 'Picture Books',
            'category' => 'books',
            'quantity' => 12,
            'condition' => 'good',
            'notes' => 'Teacher-scoped resource',
        ]);

        $this->getJson('/api/preschool/classroom-resources')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $resource->id)
            ->assertJsonPath('data.items.0.name', 'Picture Books');
    }

    public function test_teacher_preschool_can_view_one_classroom_resource(): void
    {
        $teacher = $this->createUserWithRole('teacher-preschool');
        Sanctum::actingAs($teacher);

        $resource = PreschoolClassroomResource::query()->create([
            'name' => 'Math Blocks',
            'category' => 'toys',
            'quantity' => 8,
            'condition' => 'fair',
            'notes' => 'Shared classroom material',
        ]);

        $this->getJson('/api/preschool/classroom-resources/'.$resource->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resource.id', $resource->id)
            ->assertJsonPath('data.resource.name', 'Math Blocks');
    }

    public function test_teacher_preschool_cannot_create_update_or_delete_classroom_resources(): void
    {
        $teacher = $this->createUserWithRole('teacher-preschool');
        Sanctum::actingAs($teacher);

        $resource = PreschoolClassroomResource::query()->create([
            'name' => 'Art Supplies',
            'category' => 'supplies',
            'quantity' => 4,
            'condition' => 'good',
            'notes' => null,
        ]);

        $this->postJson('/api/preschool/classroom-resources', [
            'name' => 'New Resource',
            'category' => 'books',
            'quantity' => 1,
            'condition' => 'good',
            'notes' => null,
        ])->assertForbidden();

        $this->putJson('/api/preschool/classroom-resources/'.$resource->id, [
            'name' => 'Updated Resource',
            'category' => 'books',
            'quantity' => 2,
            'condition' => 'fair',
            'notes' => 'Edited by teacher',
        ])->assertForbidden();

        $this->deleteJson('/api/preschool/classroom-resources/'.$resource->id)
            ->assertForbidden();
    }

    public function test_non_preschool_roles_are_denied_classroom_resource_access(): void
    {
        foreach (['teacher-english', 'adminenglish', 'coach'] as $roleCode) {
            $user = $this->createUserWithRole($roleCode);
            Sanctum::actingAs($user);

            $this->getJson('/api/preschool/classroom-resources')->assertForbidden();
            $this->getJson('/api/preschool/classroom-resources/1')->assertForbidden();
        }
    }

    public function test_unauthenticated_access_returns_json_authentication_response(): void
    {
        $this->getJson('/api/preschool/classroom-resources')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

}
