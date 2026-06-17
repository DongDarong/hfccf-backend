<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentFormPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_update_publish_duplicate_and_archive_form_template(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'afp-100', 'preschool.form100@hfccf.org');
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/assessment/forms', $this->buildTemplatePayload());

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_draft', true)
            ->assertJsonPath('data.sections.0.sort_order', 1)
            ->assertJsonPath('data.sections.1.sort_order', 2)
            ->assertJsonPath('data.sections.0.questions.0.sort_order', 1)
            ->assertJsonPath('data.sections.0.questions.1.sort_order', 2);

        $templateId = $create->json('data.id');

        $update = $this->putJson("/api/assessment/forms/{$templateId}", [
            'name' => 'Preschool Development Checklist v2',
            'sections' => [
                [
                    'id' => $create->json('data.sections.0.id'),
                    'code' => 'student_profile',
                    'title' => 'Student Profile Updated',
                    'sort_order' => 1,
                    'questions' => [
                        [
                            'label' => 'Child full name',
                            'question_type_key' => 'short_text',
                            'sort_order' => 1,
                        ],
                        [
                            'label' => 'Primary focus area',
                            'question_type_key' => 'dropdown',
                            'sort_order' => 2,
                            'options' => [
                                ['label' => 'Learning', 'value' => 'learning', 'score_value' => 5, 'sort_order' => 1],
                                ['label' => 'Behavior', 'value' => 'behavior', 'score_value' => 3, 'sort_order' => 2],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => $create->json('data.sections.1.id'),
                    'code' => 'family_context',
                    'title' => 'Family Context',
                    'sort_order' => 2,
                    'questions' => [
                        [
                            'label' => 'Guardian contact',
                            'question_type_key' => 'short_text',
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $update
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Preschool Development Checklist v2')
            ->assertJsonPath('data.sections.0.title', 'Student Profile Updated');

        $updatedSectionIds = collect($update->json('data.sections'))->pluck('id')->all();
        $updatedQuestionIds = collect($update->json('data.sections'))
            ->flatMap(fn (array $section) => $section['questions'])
            ->pluck('id')
            ->all();

        $reorderedSectionIds = array_reverse($updatedSectionIds);
        $this->postJson("/api/assessment/forms/{$templateId}/sections/reorder", [
            'ids' => $reorderedSectionIds,
        ])->assertOk()->assertJsonPath('success', true);

        $reorderedSections = $this->getJson("/api/assessment/forms/{$templateId}")
            ->assertOk()
            ->json('data.sections');

        $this->assertSame($reorderedSectionIds[0], $reorderedSections[0]['id']);
        $this->assertSame($reorderedSectionIds[1], $reorderedSections[1]['id']);

        $reorderedQuestionIds = array_reverse($updatedQuestionIds);
        $this->postJson("/api/assessment/forms/{$templateId}/questions/reorder", [
            'ids' => $reorderedQuestionIds,
        ])->assertOk()->assertJsonPath('success', true);

        $reorderedTemplate = $this->getJson("/api/assessment/forms/{$templateId}")
            ->assertOk()
            ->json('data');

        $flattenedQuestionIds = collect($reorderedTemplate['sections'])
            ->flatMap(fn (array $section) => $section['questions'])
            ->pluck('id')
            ->all();

        $this->assertSame($reorderedQuestionIds, $flattenedQuestionIds);

        $publish = $this->postJson("/api/assessment/forms/{$templateId}/publish", [
            'publish_notes' => 'Ready for Preschool review',
            'version_notes' => 'Version 1 for the new cycle',
            'review_notes' => 'Checked by the curriculum lead',
        ]);
        $publish
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.current_version', 1)
            ->assertJsonPath('data.version_notes', 'Version 1 for the new cycle')
            ->assertJsonPath('data.review_notes', 'Checked by the curriculum lead');

        $this->assertDatabaseHas('assessment_form_versions', [
            'template_id' => $templateId,
            'version_number' => 1,
            'is_current' => 1,
        ]);

        $versions = $this->getJson("/api/assessment/forms/{$templateId}/versions")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $versions);
        $this->assertSame(2, $versions[0]['sections_count']);
        $this->assertSame(3, $versions[0]['questions_count']);
        $this->assertSame('published', $versions[0]['status']);
        $this->assertSame('Ready for Preschool review', $versions[0]['publish_notes']);
        $this->assertSame('Version 1 for the new cycle', $versions[0]['version_notes']);
        $this->assertSame('Checked by the curriculum lead', $versions[0]['review_notes']);
        $this->assertArrayHasKey('snapshot', $versions[0]);
        $this->assertSame('Preschool Development Checklist v2', $versions[0]['snapshot']['template']['name']);
        $this->assertSame('short_text', $versions[0]['snapshot']['sections'][0]['questions'][0]['question_type_key']);

        $duplicate = $this->postJson("/api/assessment/forms/{$templateId}/duplicate", [
            'duplicate_notes' => 'Duplicate for spring cohort',
            'version_notes' => 'Copied from published baseline',
            'review_notes' => 'Duplicated after approval',
        ]);
        $duplicate
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_draft', true)
            ->assertJsonPath('data.duplicated_from_template_id', $templateId)
            ->assertJsonPath('data.duplicated_from_version', 1)
            ->assertJsonPath('data.version_notes', 'Copied from published baseline')
            ->assertJsonPath('data.review_notes', 'Duplicated after approval');

        $copyId = $duplicate->json('data.id');

        $this->postJson("/api/assessment/forms/{$copyId}/archive")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.is_archived', true);

        $this->postJson("/api/assessment/forms/{$copyId}/restore", [
            'restore_notes' => 'Restore for correction',
            'version_notes' => 'Restored draft version note',
            'review_notes' => 'Restored and ready for edits',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.restored_from_template_id', $copyId)
            ->assertJsonPath('data.version_notes', 'Restored draft version note')
            ->assertJsonPath('data.review_notes', 'Restored and ready for edits');
    }

    public function test_admin_can_submit_review_move_through_queue_approve_and_publish_form_template(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'afp-140', 'preschool.form140@hfccf.org');
        Sanctum::actingAs($admin);

        $template = $this->postJson('/api/assessment/forms', $this->buildTemplatePayload())
            ->assertCreated();
        $templateId = $template->json('data.id');

        $submit = $this->postJson("/api/assessment/forms/{$templateId}/submit-review", [
            'review_notes' => 'Ready for preschool review queue',
        ]);

        $submit
            ->assertOk()
            ->assertJsonPath('data.review_status', 'submitted')
            ->assertJsonPath('data.submitted_by.id', $admin->id);

        $queue = $this->getJson('/api/assessment/forms/review-queue')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_review', 1);

        $this->assertSame($templateId, $queue->json('data.items.0.id'));
        $this->assertSame('submitted', $queue->json('data.items.0.review_status'));

        $this->postJson("/api/assessment/forms/{$templateId}/start-review")
            ->assertOk()
            ->assertJsonPath('data.review_status', 'in_review')
            ->assertJsonPath('data.review_started_by.id', $admin->id);

        $history = $this->getJson("/api/assessment/forms/{$templateId}/review-history")
            ->assertOk();
        $this->assertNotEmpty($history->json('data'));

        $this->postJson("/api/assessment/forms/{$templateId}/approve", [
            'review_notes' => 'Approved for publication',
        ])
            ->assertOk()
            ->assertJsonPath('data.review_status', 'approved')
            ->assertJsonPath('data.reviewed_by.id', $admin->id);

        $this->postJson("/api/assessment/forms/{$templateId}/publish", [
            'publish_notes' => 'Publish after review approval',
            'version_notes' => 'Reviewed and approved',
            'review_notes' => 'Approved for publication',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.review_status', 'approved');

        $this->assertDatabaseHas('assessment_audit_logs', [
            'entity_type' => \App\Models\AssessmentFormTemplate::class,
            'entity_id' => $templateId,
            'action' => 'form.review.approved',
        ]);
    }

    public function test_admin_can_reject_review_and_keep_template_editable(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'afp-150', 'preschool.form150@hfccf.org');
        Sanctum::actingAs($admin);

        $template = $this->postJson('/api/assessment/forms', $this->buildTemplatePayload())
            ->assertCreated();
        $templateId = $template->json('data.id');

        $this->postJson("/api/assessment/forms/{$templateId}/submit-review", [
            'review_notes' => 'Needs some corrections',
        ])->assertOk();

        $this->postJson("/api/assessment/forms/{$templateId}/reject", [
            'rejection_reason' => 'Please correct the question order and scoring.',
        ])
            ->assertOk()
            ->assertJsonPath('data.review_status', 'rejected');

        $this->putJson("/api/assessment/forms/{$templateId}", [
            'name' => 'Preschool Development Checklist Revised',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Preschool Development Checklist Revised');

        $this->postJson("/api/assessment/forms/{$templateId}/publish")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_publish_requires_at_least_one_section_and_question(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'afp-110', 'preschool.form110@hfccf.org');
        Sanctum::actingAs($admin);

        $emptyTemplate = $this->postJson('/api/assessment/forms', [
            'name' => 'Empty Preschool Form',
            'category' => 'preschool_assessment',
        ])->assertCreated();

        $emptyTemplateId = $emptyTemplate->json('data.id');

        $this->postJson("/api/assessment/forms/{$emptyTemplateId}/publish")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $sectionOnlyTemplate = $this->postJson('/api/assessment/forms', [
            'name' => 'Section Only Form',
            'category' => 'preschool_assessment',
            'sections' => [
                [
                    'title' => 'Section One',
                    'sort_order' => 1,
                    'questions' => [],
                ],
            ],
        ])->assertCreated();

        $sectionOnlyTemplateId = $sectionOnlyTemplate->json('data.id');

        $this->postJson("/api/assessment/forms/{$sectionOnlyTemplateId}/publish")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_published_template_cannot_be_edited_directly_or_through_sections(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'afp-120', 'preschool.form120@hfccf.org');
        Sanctum::actingAs($admin);

        $template = $this->postJson('/api/assessment/forms', $this->buildTemplatePayload())
            ->assertCreated();

        $templateId = $template->json('data.id');

        $this->postJson("/api/assessment/forms/{$templateId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->putJson("/api/assessment/forms/{$templateId}", [
            'name' => 'Should Not Save',
        ])->assertStatus(422);

        $this->postJson("/api/assessment/forms/{$templateId}/sections", [
            'title' => 'Forbidden Section',
        ])->assertStatus(422);
    }

    public function test_teacher_cannot_manage_form_templates(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'afp-130', 'preschool.teacher130@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/assessment/forms', $this->buildTemplatePayload())
            ->assertForbidden();

        $this->getJson('/api/assessment/forms/review-queue')
            ->assertForbidden();
    }

    private function buildTemplatePayload(): array
    {
        return [
            'name' => 'Preschool Development Checklist',
            'name_kh' => 'បញ្ជីត្រួតពិនិត្យការអភិវឌ្ឍន៍មត្តេយ្យ',
            'description' => 'Reusable form template for Preschool assessments.',
            'description_kh' => 'គំរូសម្រាប់បង្កើតទម្រង់វាយតម្លៃមត្តេយ្យឡើងវិញបាន។',
            'category' => 'preschool_assessment',
            'settings' => [
                'module' => 'preschool',
                'builder' => true,
            ],
            'sections' => [
                [
                    'code' => 'student_profile',
                    'title' => 'Student Profile',
                    'title_kh' => 'ប្រវត្តិសិស្ស',
                    'description' => 'Basic student profile data.',
                    'description_kh' => 'ព័ត៌មានមូលដ្ឋានរបស់សិស្ស។',
                    'sort_order' => 1,
                    'questions' => [
                        [
                            'code' => 'child_name',
                            'label' => 'Child Name',
                            'label_kh' => 'ឈ្មោះកុមារ',
                            'question_type_key' => 'short_text',
                            'sort_order' => 1,
                            'validation_rules' => [
                                'required' => true,
                            ],
                        ],
                        [
                            'code' => 'focus_area',
                            'label' => 'Primary Focus Area',
                            'label_kh' => 'វិស័យផ្តោតសំខាន់',
                            'question_type_key' => 'dropdown',
                            'sort_order' => 2,
                            'options' => [
                                [
                                    'label' => 'Learning',
                                    'label_kh' => 'ការសិក្សា',
                                    'value' => 'learning',
                                    'score_value' => 5,
                                    'sort_order' => 1,
                                ],
                                [
                                    'label' => 'Behavior',
                                    'label_kh' => 'អាកប្បកិរិយា',
                                    'value' => 'behavior',
                                    'score_value' => 3,
                                    'sort_order' => 2,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'family_context',
                    'title' => 'Family Context',
                    'title_kh' => 'បរិបទគ្រួសារ',
                    'description' => 'Home and guardian context.',
                    'description_kh' => 'បរិបទគ្រួសារ និងអាណាព្យាបាល។',
                    'sort_order' => 2,
                    'questions' => [
                        [
                            'code' => 'guardian_contact',
                            'label' => 'Guardian Contact',
                            'label_kh' => 'ទំនាក់ទំនងអាណាព្យាបាល',
                            'question_type_key' => 'short_text',
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => $roleCode.'-'.$id,
            'email' => $email,
            'phone' => '+855 12 555 555',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        $rows = $role->permissions->map(static fn ($permission) => [
            'user_id' => $user->id,
            'permission_code' => $permission->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }

        return $user;
    }
}
