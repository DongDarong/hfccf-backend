<?php

namespace App\Services;

use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentFormSection;
use App\Models\AssessmentQuestion;
use Illuminate\Support\Facades\DB;

class AssessmentFormService
{
    public function publishForm(AssessmentFormTemplate $template): AssessmentFormVersion
    {
        return DB::transaction(function () use ($template) {
            $versionNumber = $template->versions()->max('version_number') + 1;

            $snapshot = [
                'sections' => $template->sections()->with(['questions.options', 'questions.matrixRows'])->get()->toArray(),
            ];

            $version = $template->versions()->create([
                'version_number' => $versionNumber,
                'snapshot'       => json_encode($snapshot),
                'published_by'   => auth()->id(),
                'published_at'   => now(),
            ]);

            $template->update([
                'status'          => 'published',
                'current_version' => $versionNumber,
            ]);

            return $version;
        });
    }

    public function duplicateForm(AssessmentFormTemplate $template): AssessmentFormTemplate
    {
        return DB::transaction(function () use ($template) {
            $newTemplate = $template->replicate(['status', 'current_version', 'deleted_at']);
            $newTemplate->name    = $template->name . ' (Copy)';
            $newTemplate->status  = 'draft';
            $newTemplate->created_by = auth()->id();
            $newTemplate->save();

            foreach ($template->sections()->with('questions.options')->get() as $section) {
                $newSection = $section->replicate(['form_template_id']);
                $newSection->form_template_id = $newTemplate->id;
                $newSection->save();

                foreach ($section->questions as $question) {
                    $newQuestion = $question->replicate(['section_id']);
                    $newQuestion->section_id = $newSection->id;
                    $newQuestion->save();

                    foreach ($question->options as $option) {
                        $newOption = $option->replicate(['question_id']);
                        $newOption->question_id = $newQuestion->id;
                        $newOption->save();
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
                ->where('form_template_id', $template->id)
                ->update(['order' => $index + 1]);
        }
    }

    public function reorderQuestions(AssessmentFormTemplate $template, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            AssessmentQuestion::whereHas('section', fn ($q) => $q->where('form_template_id', $template->id))
                ->where('id', $id)
                ->update(['order' => $index + 1]);
        }
    }
}
