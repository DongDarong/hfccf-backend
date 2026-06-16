<?php

namespace App\Services;

use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentFormSection;
use App\Models\AssessmentQuestionType;
use App\Models\AssessmentQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentFormService
{
    public function __construct(private AssessmentLifecycleService $lifecycle) {}

    public function syncTemplateTree(AssessmentFormTemplate $template, array $sections = [], ?User $actor = null): AssessmentFormTemplate
    {
        return DB::transaction(function () use ($template, $sections, $actor) {
            $template->loadMissing(['sections.questions.options', 'sections.questions.matrixRows']);

            $template->sections()->each(function (AssessmentFormSection $section): void {
                $section->questions()->each(function (AssessmentQuestion $question): void {
                    $question->options()->delete();
                    $question->matrixRows()->delete();
                    $question->delete();
                });
                $section->children()->delete();
                $section->delete();
            });

            foreach ($sections as $sectionIndex => $sectionData) {
                $section = $template->sections()->create([
                    'code' => $sectionData['code'] ?? Str::slug((string) ($sectionData['title'] ?? 'section')).'-'.($sectionIndex + 1),
                    'title' => $sectionData['title'] ?? 'Section '.($sectionIndex + 1),
                    'title_kh' => $sectionData['title_kh'] ?? null,
                    'description' => $sectionData['description'] ?? null,
                    'description_kh' => $sectionData['description_kh'] ?? null,
                    'sort_order' => (int) ($sectionData['sort_order'] ?? $sectionData['order'] ?? ($sectionIndex + 1)),
                    'is_repeatable' => (bool) ($sectionData['is_repeatable'] ?? false),
                    'max_repeats' => $sectionData['max_repeats'] ?? null,
                    'print_visible' => (bool) ($sectionData['print_visible'] ?? true),
                    'scoring_weight' => $sectionData['scoring_weight'] ?? 1,
                    'settings' => $sectionData['settings'] ?? [],
                ]);

                foreach (($sectionData['questions'] ?? []) as $questionIndex => $questionData) {
                    $questionType = $this->resolveQuestionType($questionData);
                    $question = $section->questions()->create([
                        'uuid' => (string) Str::uuid(),
                        'template_id' => $template->id,
                        'question_type_id' => $questionType?->id,
                        'code' => $questionData['code'] ?? Str::slug((string) ($questionData['label'] ?? 'question')).'-'.($questionIndex + 1),
                        'label' => $questionData['label'] ?? $questionData['question_text'] ?? 'Question '.($questionIndex + 1),
                        'label_kh' => $questionData['label_kh'] ?? null,
                        'help_text' => $questionData['help_text'] ?? null,
                        'help_text_kh' => $questionData['help_text_kh'] ?? null,
                        'placeholder' => $questionData['placeholder'] ?? null,
                        'placeholder_kh' => $questionData['placeholder_kh'] ?? null,
                        'sort_order' => (int) ($questionData['sort_order'] ?? $questionData['order'] ?? ($questionIndex + 1)),
                        'is_required' => (bool) ($questionData['is_required'] ?? false),
                        'is_scored' => (bool) ($questionData['is_scored'] ?? false),
                        'max_score' => $questionData['max_score'] ?? null,
                        'scoring_weight' => $questionData['scoring_weight'] ?? 1,
                        'print_visible' => (bool) ($questionData['print_visible'] ?? true),
                        'validation_rules' => $questionData['validation_rules'] ?? $questionData['validationRules'] ?? [],
                        'conditional_logic' => $questionData['conditional_logic'] ?? [],
                        'calculation_formula' => $questionData['calculation_formula'] ?? null,
                        'settings' => $questionData['settings'] ?? $questionData['config'] ?? [],
                    ]);

                    foreach (($questionData['options'] ?? []) as $optionIndex => $optionData) {
                        $question->options()->create([
                            'label' => $optionData['label'] ?? $optionData['option_text'] ?? 'Option '.($optionIndex + 1),
                            'label_kh' => $optionData['label_kh'] ?? null,
                            'value' => $optionData['value'] ?? Str::slug((string) ($optionData['label'] ?? 'option')),
                            'score_value' => $optionData['score_value'] ?? 0,
                            'risk_tag' => $optionData['risk_tag'] ?? null,
                            'color_code' => $optionData['color_code'] ?? null,
                            'sort_order' => (int) ($optionData['sort_order'] ?? $optionData['order'] ?? ($optionIndex + 1)),
                            'is_other' => (bool) ($optionData['is_other'] ?? false),
                            'settings' => $optionData['settings'] ?? [],
                        ]);
                    }
                }
            }

            $template->update([
                'updated_by' => $actor?->id ?? auth()->id(),
                'is_locked' => false,
            ]);

            return $template->fresh(['sections.questions.options', 'sections.questions.matrixRows']);
        });
    }

    public function duplicateForm(AssessmentFormTemplate $template, array $metadata = []): AssessmentFormTemplate
    {
        return DB::transaction(function () use ($template, $metadata) {
            $template->loadMissing([
                'sections.questions.options',
                'sections.questions.matrixRows',
                'scoringRules',
                'riskLevels',
                'printTemplates',
            ]);

            $newTemplate = $template->replicate([
                'deleted_at',
                'published_at',
                'published_by',
                'archived_at',
                'archived_by',
            ]);
            $newTemplate->uuid = (string) Str::uuid();
            $newTemplate->code = $this->generateCopyCode($template->code ?? 'FORM');
            $newTemplate->name = $template->name . ' (Copy)';
            $newTemplate->status = 'draft';
            $newTemplate->is_locked = false;
            $newTemplate->created_by = auth()->id();
            $newTemplate->updated_by = auth()->id();
            $newTemplate->published_at = null;
            $newTemplate->published_by = null;
            $newTemplate->archived_at = null;
            $newTemplate->archived_by = null;
            $newTemplate->duplicated_from_template_id = $template->id;
            $newTemplate->duplicated_from_version = $template->current_version;
            $newTemplate->restored_from_template_id = null;
            $newTemplate->restored_from_version = null;
            $newTemplate->version_notes = $metadata['version_notes'] ?? $metadata['duplicate_notes'] ?? $template->version_notes;
            $newTemplate->review_notes = $metadata['review_notes'] ?? null;
            $newTemplate->reviewed_by = null;
            $newTemplate->reviewed_at = null;
            $newTemplate->save();

            foreach ($template->sections()->with(['questions.options', 'questions.matrixRows'])->orderBy('sort_order')->get() as $section) {
                $newSection = $section->replicate(['deleted_at']);
                $newSection->template_id = $newTemplate->id;
                $newSection->parent_id = null;
                $newSection->created_at = now();
                $newSection->updated_at = now();
                $newSection->save();

                foreach ($section->questions as $question) {
                    $newQuestion = $question->replicate(['deleted_at']);
                    $newQuestion->template_id = $newTemplate->id;
                    $newQuestion->section_id = $newSection->id;
                    $newQuestion->parent_question_id = null;
                    $newQuestion->uuid = (string) Str::uuid();
                    $newQuestion->created_at = now();
                    $newQuestion->updated_at = now();
                    $newQuestion->save();

                    foreach ($question->options as $option) {
                        $newOption = $option->replicate(['deleted_at']);
                        $newOption->question_id = $newQuestion->id;
                        $newOption->created_at = now();
                        $newOption->updated_at = now();
                        $newOption->save();
                    }

                    foreach ($question->matrixRows as $row) {
                        $newRow = $row->replicate(['deleted_at']);
                        $newRow->question_id = $newQuestion->id;
                        $newRow->created_at = now();
                        $newRow->updated_at = now();
                        $newRow->save();
                    }
                }
            }

            foreach ($template->scoringRules as $rule) {
                $newRule = $rule->replicate(['id']);
                $newRule->template_id = $newTemplate->id;
                $newRule->save();
            }

            foreach ($template->riskLevels as $level) {
                $newLevel = $level->replicate(['id']);
                $newLevel->template_id = $newTemplate->id;
                $newLevel->save();
            }

            foreach ($template->printTemplates as $printTemplate) {
                $newPrintTemplate = $printTemplate->replicate(['deleted_at']);
                $newPrintTemplate->form_template_id = $newTemplate->id;
                $newPrintTemplate->uuid = (string) Str::uuid();
                $newPrintTemplate->created_by = auth()->id();
                $newPrintTemplate->updated_by = auth()->id();
                $newPrintTemplate->save();
            }

            return $newTemplate;
        });
    }

    public function publishForm(AssessmentFormTemplate $template, array $metadata = []): AssessmentFormVersion
    {
        return DB::transaction(function () use ($template, $metadata) {
            $template->loadMissing(['sections.questions.options', 'sections.questions.matrixRows']);

            $template->version_notes = $metadata['version_notes'] ?? $metadata['publish_notes'] ?? $template->version_notes;
            $template->review_notes = $metadata['review_notes'] ?? $template->review_notes;
            $template->reviewed_by = auth()->id();
            $template->reviewed_at = now();
            $template->save();

            $version = $this->lifecycle->publishTemplate(
                $template,
                $metadata['publish_notes'] ?? $metadata['change_summary'] ?? $metadata['version_notes'] ?? null
            );
            $template->update([
                'status' => 'published',
                'published_at' => $version->published_at,
                'published_by' => $version->published_by,
                'updated_by' => auth()->id(),
            ]);
            $this->lifecycle->recordAudit(
                entityType: AssessmentFormTemplate::class,
                entityId: $template->id,
                action: 'form.versioned',
                entityLabel: $template->name,
                newValue: [
                    'version_id' => $version->id,
                    'version' => $version->version_number,
                    'status' => 'published',
                ],
                meta: ['change_summary' => $metadata['publish_notes'] ?? $metadata['change_summary'] ?? $metadata['version_notes'] ?? null],
            );

            return $version;
        });
    }

    public function archiveForm(AssessmentFormTemplate $template, ?User $actor = null): AssessmentFormTemplate
    {
        $template->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $actor?->id ?? auth()->id(),
            'updated_by' => $actor?->id ?? auth()->id(),
        ]);

        return $template->fresh();
    }

    public function restoreForm(AssessmentFormTemplate $template, ?User $actor = null, array $metadata = []): AssessmentFormTemplate
    {
        $template->update([
            'status' => 'draft',
            'archived_at' => null,
            'archived_by' => null,
            'updated_by' => $actor?->id ?? auth()->id(),
            'restored_from_template_id' => $metadata['restored_from_template_id'] ?? $template->id,
            'restored_from_version' => $metadata['restored_from_version'] ?? $template->current_version,
            'version_notes' => $metadata['version_notes'] ?? $metadata['restore_notes'] ?? $template->version_notes,
            'review_notes' => $metadata['review_notes'] ?? $template->review_notes,
        ]);

        return $template->fresh();
    }

    public function createDraftFromPublished(AssessmentFormTemplate $template): AssessmentFormTemplate
    {
        return $this->duplicateForm($template);
    }

    private function resolveQuestionType(array $questionData): ?AssessmentQuestionType
    {
        $typeKey = $questionData['question_type_key'] ?? $questionData['answerType'] ?? $questionData['answer_type'] ?? null;

        if (blank($typeKey)) {
            return AssessmentQuestionType::query()->where('key', 'short_text')->first();
        }

        $normalized = match ($typeKey) {
            'shortText' => 'short_text',
            'longText' => 'long_text',
            'rating' => 'rating_scale',
            'table' => 'dynamic_table',
            default => $typeKey,
        };

        return AssessmentQuestionType::query()->where('key', $normalized)->first()
            ?? AssessmentQuestionType::query()->where('key', 'short_text')->first();
    }

    public function reorderSections(AssessmentFormTemplate $template, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            AssessmentFormSection::where('id', $id)
                ->where('template_id', $template->id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    public function reorderQuestions(AssessmentFormTemplate $template, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            AssessmentQuestion::whereHas('section', fn ($q) => $q->where('template_id', $template->id))
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    private function generateCopyCode(string $baseCode): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $baseCode) ?: 'FORM');
        $suffix = strtoupper(Str::random(4));

        return trim($base, '-') . '-COPY-' . $suffix;
    }
}
