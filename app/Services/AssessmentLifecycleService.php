<?php

namespace App\Services;

use App\Models\AssessmentAuditLog;
use App\Models\AssessmentExportLog;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Str;

class AssessmentLifecycleService
{
    public function buildTemplateSnapshot(AssessmentFormTemplate $template): array
    {
        $template->loadMissing([
            'sections.questions.options',
            'sections.questions.matrixRows',
            'sections.questions.questionType',
            'scoringRules',
            'riskLevels',
            'printTemplates',
            'reviewedBy',
        ]);

        return [
            'template' => [
                'id'         => $template->id,
                'uuid'       => $template->uuid,
                'code'       => $template->code,
                'name'       => $template->name,
                'module'     => $template->module,
                'status'     => $template->status,
                'review_status' => $template->review_status,
                'settings'   => $template->settings,
                'version'    => $template->current_version,
                'version_notes' => $template->version_notes,
                'review_notes'  => $template->review_notes,
                'submitted_by'  => $template->submitted_by,
                'submitted_by_name' => trim(($template->submittedBy?->first_name ?? '').' '.($template->submittedBy?->last_name ?? '')) ?: null,
                'submitted_at'  => $template->submitted_at?->toIso8601String(),
                'review_started_by' => $template->review_started_by,
                'review_started_by_name' => trim(($template->reviewStartedBy?->first_name ?? '').' '.($template->reviewStartedBy?->last_name ?? '')) ?: null,
                'review_started_at' => $template->review_started_at?->toIso8601String(),
                'reviewed_by'   => $template->reviewed_by,
                'reviewed_by_name' => trim(($template->reviewedBy?->first_name ?? '').' '.($template->reviewedBy?->last_name ?? '')) ?: null,
                'reviewed_at'   => $template->reviewed_at?->toIso8601String(),
                'created_by' => $template->created_by,
                'updated_by' => $template->updated_by,
                'published_at' => $template->published_at?->toIso8601String(),
                'published_by' => $template->published_by,
                'archived_at' => $template->archived_at?->toIso8601String(),
                'archived_by' => $template->archived_by,
                'duplicated_from_template_id' => $template->duplicated_from_template_id,
                'duplicated_from_version' => $template->duplicated_from_version,
                'restored_from_template_id' => $template->restored_from_template_id,
                'restored_from_version' => $template->restored_from_version,
            ],
            'sections' => $template->sections->map(fn ($section) => [
                'id'          => $section->id,
                'template_id' => $section->template_id,
                'code'        => $section->code,
                'title'       => $section->title,
                'description' => $section->description,
                'sort_order'  => $section->sort_order,
                'settings'    => $section->settings,
                'questions'   => $section->questions->map(fn ($question) => [
                    'id'                 => $question->id,
                    'template_id'        => $question->template_id,
                    'section_id'         => $question->section_id,
                    'question_type_id'    => $question->question_type_id,
                    'question_type_key'   => $question->questionType?->key,
                    'question_type_label' => $question->questionType?->label,
                    'label'              => $question->label,
                    'code'               => $question->code,
                    'help_text'          => $question->help_text,
                    'placeholder'        => $question->placeholder,
                    'sort_order'         => $question->sort_order,
                    'is_required'        => $question->is_required,
                    'is_scored'          => $question->is_scored,
                    'max_score'          => $question->max_score,
                    'scoring_weight'     => $question->scoring_weight,
                    'print_visible'      => $question->print_visible,
                    'validation_rules'   => $question->validation_rules,
                    'conditional_logic'   => $question->conditional_logic,
                    'calculation_formula'=> $question->calculation_formula,
                    'settings'           => $question->settings,
                    'options'            => $question->options->map(fn ($option) => [
                        'id'          => $option->id,
                        'label'       => $option->label,
                        'value'       => $option->value,
                        'score_value' => $option->score_value,
                        'risk_tag'    => $option->risk_tag,
                        'color_code'  => $option->color_code,
                        'sort_order'  => $option->sort_order,
                        'is_other'    => $option->is_other,
                        'settings'    => $option->settings,
                    ])->values(),
                    'matrix_rows'        => $question->matrixRows->map(fn ($row) => [
                        'id'         => $row->id,
                        'label'      => $row->label,
                        'label_kh'   => $row->label_kh,
                        'sort_order' => $row->sort_order,
                    ])->values(),
                ])->values(),
            ])->values(),
            'scoring_rules' => $template->scoringRules->map(fn ($rule) => [
                'id'         => $rule->id,
                'scope'      => $rule->scope,
                'scope_id'   => $rule->scope_id,
                'rule_type'  => $rule->rule_type,
                'formula'    => $rule->formula,
                'max_score'  => $rule->max_score,
                'pass_score' => $rule->pass_score,
                'settings'   => $rule->settings,
            ])->values(),
            'risk_levels' => $template->riskLevels->map(fn ($level) => [
                'id'          => $level->id,
                'label'       => $level->label,
                'key'         => $level->key,
                'min_score'   => $level->min_score,
                'max_score'   => $level->max_score,
                'color_code'  => $level->color_code,
                'sort_order'  => $level->sort_order,
                'description' => $level->description,
            ])->values(),
            'print_templates' => $template->printTemplates->map(fn ($printTemplate) => [
                'id'           => $printTemplate->id,
                'uuid'         => $printTemplate->uuid,
                'name'         => $printTemplate->name,
                'format'       => $printTemplate->format,
                'page_size'    => $printTemplate->page_size,
                'orientation'  => $printTemplate->orientation,
                'is_default'   => $printTemplate->is_default,
                'status'       => $printTemplate->status,
                'blocks'       => $printTemplate->blocks,
                'styles'       => $printTemplate->styles,
            ])->values(),
        ];
    }

    public function publishTemplate(AssessmentFormTemplate $template, ?string $changeSummary = null): AssessmentFormVersion
    {
        $snapshot = $this->buildTemplateSnapshot($template);
        $nextVersion = (int) ($template->versions()->max('version_number') ?? 0) + 1;

        $template->versions()->update(['is_current' => false]);

        $version = $template->versions()->create([
            'template_id'    => $template->id,
            'version_number' => $nextVersion,
            'label'          => 'v'.$nextVersion,
            'snapshot'       => $snapshot,
            'change_summary' => $changeSummary,
            'published_by'   => (string) auth()->id(),
            'published_at'   => now(),
            'is_current'     => true,
        ]);

        $template->update([
            'status' => 'published',
            'published_at' => $version->published_at,
            'published_by' => $version->published_by,
        ]);
        $this->recordAudit(
            entityType: AssessmentFormTemplate::class,
            entityId: $template->id,
            action: 'form.published',
            entityLabel: $template->name,
            newValue: ['version' => $nextVersion, 'status' => 'published'],
            meta: ['change_summary' => $changeSummary],
        );

        return $version;
    }

    public function recordAudit(
        string $entityType,
        string|int $entityId,
        string $action,
        ?string $entityLabel = null,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?array $meta = null,
    ): AssessmentAuditLog {
        return AssessmentAuditLog::create([
            'user_id'      => (string) auth()->id(),
            'action'       => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'entity_label' => $entityLabel,
            'old_value'    => $oldValue ? json_encode($oldValue) : null,
            'new_value'    => $newValue ? json_encode($newValue) : null,
            'ip_address'   => request()?->ip(),
            'user_agent'   => request()?->userAgent(),
            'meta'         => $meta ?? [],
        ]);
    }

    public function recordSubmissionAudit(
        AssessmentSubmission $submission,
        string $action,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $entityLabel = null,
        ?array $meta = null,
    ): AssessmentAuditLog {
        return $this->recordAudit(
            entityType: AssessmentSubmission::class,
            entityId: $submission->id,
            action: $action,
            entityLabel: $entityLabel ?? ('Assessment Submission #'.$submission->id),
            oldValue: $oldValue,
            newValue: $newValue,
            meta: $meta,
        );
    }

    public function startExport(array $data): AssessmentExportLog
    {
        return AssessmentExportLog::create([
            'uuid'            => (string) Str::uuid(),
            'initiated_by'    => (string) (auth()->id() ?? $data['initiated_by'] ?? ''),
            'export_type'     => $data['export_type'],
            'scope'           => $data['scope'] ?? 'single',
            'submission_ids'  => $data['submission_ids'] ?? null,
            'print_template_id' => $data['print_template_id'] ?? null,
            'status'          => 'queued',
            'started_at'      => now(),
            'expires_at'      => $data['expires_at'] ?? now()->addDays(7),
            'meta'            => $data['meta'] ?? null,
        ]);
    }

    public function markExportProcessing(AssessmentExportLog $exportLog): AssessmentExportLog
    {
        $exportLog->update([
            'status'     => 'processing',
            'started_at' => $exportLog->started_at ?? now(),
        ]);

        return $exportLog->fresh();
    }

    public function markExportCompleted(AssessmentExportLog $exportLog, string $filePath, ?int $fileSize = null, ?array $meta = null): AssessmentExportLog
    {
        $exportLog->update([
            'status'       => 'completed',
            'file_path'    => $filePath,
            'file_size'    => $fileSize,
            'completed_at' => now(),
            'meta'         => array_merge($exportLog->meta ?? [], $meta ?? []),
        ]);

        return $exportLog->fresh();
    }

    public function markExportFailed(AssessmentExportLog $exportLog, string $errorMessage, ?array $meta = null): AssessmentExportLog
    {
        $exportLog->update([
            'status'        => 'failed',
            'error_message' => $errorMessage,
            'completed_at'  => now(),
            'meta'          => array_merge($exportLog->meta ?? [], $meta ?? []),
        ]);

        return $exportLog->fresh();
    }
}
