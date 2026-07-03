<?php

namespace App\Services;

use App\Models\PreschoolWorkflowDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PreschoolWorkflowDefinitionService
{
    public const DEFAULT_DEFINITIONS = [
        [
            'key' => 'enrollment_admission',
            'name' => 'Enrollment Admission',
            'description' => 'Admission workflow template for preschool enrollment applications.',
            'domain' => 'preschool',
            'steps' => [
                ['key' => 'submitted', 'name' => 'Submitted', 'sort_order' => 1, 'step_type' => 'start'],
                ['key' => 'document_review', 'name' => 'Document Review', 'sort_order' => 2, 'step_type' => 'review', 'assigned_role' => 'adminpreschool', 'sla_hours' => 48],
                ['key' => 'interview', 'name' => 'Interview', 'sort_order' => 3, 'step_type' => 'action', 'assigned_role' => 'adminpreschool', 'sla_hours' => 72],
                ['key' => 'decision', 'name' => 'Decision', 'sort_order' => 4, 'step_type' => 'approval', 'assigned_role' => 'adminpreschool', 'sla_hours' => 48],
                ['key' => 'enrolled', 'name' => 'Enrolled', 'sort_order' => 5, 'step_type' => 'final'],
            ],
        ],
        [
            'key' => 'health_alert_resolution',
            'name' => 'Health Alert Resolution',
            'description' => 'Track medical alert acknowledgment, assignment, and closure.',
            'domain' => 'preschool',
            'steps' => [
                ['key' => 'created', 'name' => 'Created', 'sort_order' => 1, 'step_type' => 'start'],
                ['key' => 'acknowledged', 'name' => 'Acknowledged', 'sort_order' => 2, 'step_type' => 'review', 'assigned_role' => 'teacher-preschool', 'sla_hours' => 24],
                ['key' => 'assigned', 'name' => 'Assigned', 'sort_order' => 3, 'step_type' => 'action', 'assigned_role' => 'adminpreschool', 'sla_hours' => 24],
                ['key' => 'resolved', 'name' => 'Resolved', 'sort_order' => 4, 'step_type' => 'approval', 'assigned_role' => 'adminpreschool', 'sla_hours' => 48],
                ['key' => 'closed', 'name' => 'Closed', 'sort_order' => 5, 'step_type' => 'final'],
            ],
        ],
        [
            'key' => 'invoice_collection',
            'name' => 'Invoice Collection',
            'description' => 'Read-first billing workflow template for invoice follow-up.',
            'domain' => 'preschool',
            'steps' => [
                ['key' => 'draft', 'name' => 'Draft', 'sort_order' => 1, 'step_type' => 'start'],
                ['key' => 'issued', 'name' => 'Issued', 'sort_order' => 2, 'step_type' => 'action', 'assigned_role' => 'adminpreschool', 'sla_hours' => 24],
                ['key' => 'reminder', 'name' => 'Reminder', 'sort_order' => 3, 'step_type' => 'review', 'assigned_role' => 'adminpreschool', 'sla_hours' => 72],
                ['key' => 'paid', 'name' => 'Paid', 'sort_order' => 4, 'step_type' => 'final'],
                ['key' => 'closed', 'name' => 'Closed', 'sort_order' => 5, 'step_type' => 'final'],
            ],
        ],
        [
            'key' => 'assessment_review',
            'name' => 'Assessment Review',
            'description' => 'Template review workflow for preschool assessment forms.',
            'domain' => 'preschool',
            'steps' => [
                ['key' => 'draft', 'name' => 'Draft', 'sort_order' => 1, 'step_type' => 'start'],
                ['key' => 'review', 'name' => 'Review', 'sort_order' => 2, 'step_type' => 'review', 'assigned_role' => 'adminpreschool', 'sla_hours' => 48],
                ['key' => 'approved', 'name' => 'Approved', 'sort_order' => 3, 'step_type' => 'approval', 'assigned_role' => 'adminpreschool', 'sla_hours' => 24],
                ['key' => 'published', 'name' => 'Published', 'sort_order' => 4, 'step_type' => 'final'],
                ['key' => 'archived', 'name' => 'Archived', 'sort_order' => 5, 'step_type' => 'final'],
            ],
        ],
        [
            'key' => 'attendance_follow_up',
            'name' => 'Attendance Follow Up',
            'description' => 'Attendance alert and guardian follow-up workflow template.',
            'domain' => 'preschool',
            'steps' => [
                ['key' => 'alert_created', 'name' => 'Alert Created', 'sort_order' => 1, 'step_type' => 'start'],
                ['key' => 'guardian_contact', 'name' => 'Guardian Contact', 'sort_order' => 2, 'step_type' => 'action', 'assigned_role' => 'teacher-preschool', 'sla_hours' => 24],
                ['key' => 'follow_up_required', 'name' => 'Follow Up Required', 'sort_order' => 3, 'step_type' => 'review', 'assigned_role' => 'adminpreschool', 'sla_hours' => 48],
                ['key' => 'resolved', 'name' => 'Resolved', 'sort_order' => 4, 'step_type' => 'approval', 'assigned_role' => 'adminpreschool', 'sla_hours' => 48],
                ['key' => 'closed', 'name' => 'Closed', 'sort_order' => 5, 'step_type' => 'final'],
            ],
        ],
    ];

    public function seedDefaults(): Collection
    {
        return DB::transaction(function (): Collection {
            return collect(self::DEFAULT_DEFINITIONS)->map(function (array $definitionPayload): PreschoolWorkflowDefinition {
                $definition = PreschoolWorkflowDefinition::query()->updateOrCreate(
                    ['key' => $definitionPayload['key']],
                    Arr::only($definitionPayload, ['name', 'description', 'domain']) + [
                        'is_active' => true,
                        'config' => Arr::except($definitionPayload, ['steps']),
                    ],
                );

                foreach ($definitionPayload['steps'] as $stepPayload) {
                    $definition->steps()->updateOrCreate(
                        ['key' => $stepPayload['key']],
                        [
                            'name' => $stepPayload['name'],
                            'sort_order' => $stepPayload['sort_order'],
                            'step_type' => $stepPayload['step_type'],
                            'assigned_role' => $stepPayload['assigned_role'] ?? null,
                            'sla_hours' => $stepPayload['sla_hours'] ?? null,
                            'config' => Arr::except($stepPayload, ['key', 'name', 'sort_order', 'step_type', 'assigned_role', 'sla_hours']),
                        ],
                    );
                }

                return $definition->load('steps');
            });
        });
    }

    public function findByKey(string $key): ?PreschoolWorkflowDefinition
    {
        $key = trim($key);

        if ($key === '') {
            return null;
        }

        return PreschoolWorkflowDefinition::query()
            ->with(['steps' => fn ($query) => $query->orderBy('sort_order')])
            ->where('key', $key)
            ->first();
    }

    public function listActive(): Collection
    {
        return PreschoolWorkflowDefinition::query()
            ->with(['steps' => fn ($query) => $query->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
