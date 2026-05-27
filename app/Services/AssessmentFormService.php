<?php

namespace App\Services;

use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentFormSection;
use App\Models\AssessmentQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentFormService
{
    public function __construct(private AssessmentLifecycleService $lifecycle) {}

    public function publishForm(AssessmentFormTemplate $template): AssessmentFormVersion
    {
        return DB::transaction(function () use ($template) {
            $version = $this->lifecycle->publishTemplate($template);
            $this->lifecycle->recordAudit(
                entityType: AssessmentFormTemplate::class,
                entityId: $template->id,
                action: 'form.versioned',
                entityLabel: $template->name,
                newValue: [
                    'version_id' => $version->id,
                    'version'    => $version->version_number,
                ],
            );

            return $version;
        });
    }

    public function duplicateForm(AssessmentFormTemplate $template): AssessmentFormTemplate
    {
        return DB::transaction(function () use ($template) {
            $newTemplate = $template->replicate(['deleted_at']);
            $newTemplate->uuid = (string) Str::uuid();
            $newTemplate->code = $this->generateCopyCode($template->code ?? 'FORM');
            $newTemplate->name = $template->name . ' (Copy)';
            $newTemplate->status = 'draft';
            $newTemplate->is_locked = false;
            $newTemplate->created_by = auth()->id();
            $newTemplate->updated_by = auth()->id();
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

            return $newTemplate;
        });
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
