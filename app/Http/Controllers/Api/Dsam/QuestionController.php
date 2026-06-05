<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Models\Dsam\FormSection;
use App\Models\Dsam\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'adminpreschool'];

    public function index(Request $request, FormSection $dsamSection): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        return $this->ok(
            $dsamSection->allQuestions()->with('questionType', 'options', 'conditionalChildren.options')->get()
        );
    }

    public function store(Request $request, FormSection $dsamSection): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamSection->formTemplate->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'question_type_id'   => ['required', 'integer', 'exists:dsam_question_types,id'],
            'parent_question_id' => ['sometimes', 'nullable', 'integer', 'exists:dsam_questions,id'],
            'trigger_option_id'  => ['sometimes', 'nullable', 'integer', 'exists:dsam_question_options,id'],
            'label'              => ['required', 'string'],
            'label_kh'           => ['sometimes', 'nullable', 'string'],
            'placeholder'        => ['sometimes', 'nullable', 'string', 'max:500'],
            'placeholder_kh'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'help_text'          => ['sometimes', 'nullable', 'string'],
            'help_text_kh'       => ['sometimes', 'nullable', 'string'],
            'is_required'        => ['sometimes', 'boolean'],
            'is_scored'          => ['sometimes', 'boolean'],
            'max_score'          => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'validation_rules'   => ['sometimes', 'nullable', 'array'],
            'config'             => ['sometimes', 'nullable', 'array'],
            'scoring_config'     => ['sometimes', 'nullable', 'array'],
        ]);

        $maxOrder = $dsamSection->allQuestions()->max('order_index') ?? -1;

        $question = $dsamSection->allQuestions()->create([
            ...$validated,
            'order_index' => $maxOrder + 1,
        ]);

        return $this->created(
            $question->load('questionType', 'options'),
            'Question created.',
        );
    }

    public function update(Request $request, FormSection $dsamSection, Question $question): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamSection->formTemplate->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'question_type_id'  => ['sometimes', 'integer', 'exists:dsam_question_types,id'],
            'parent_question_id'=> ['sometimes', 'nullable', 'integer', 'exists:dsam_questions,id'],
            'trigger_option_id' => ['sometimes', 'nullable', 'integer', 'exists:dsam_question_options,id'],
            'label'             => ['sometimes', 'string'],
            'label_kh'          => ['sometimes', 'nullable', 'string'],
            'placeholder'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'placeholder_kh'    => ['sometimes', 'nullable', 'string', 'max:500'],
            'help_text'         => ['sometimes', 'nullable', 'string'],
            'help_text_kh'      => ['sometimes', 'nullable', 'string'],
            'is_required'       => ['sometimes', 'boolean'],
            'is_scored'         => ['sometimes', 'boolean'],
            'max_score'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'validation_rules'  => ['sometimes', 'nullable', 'array'],
            'config'            => ['sometimes', 'nullable', 'array'],
            'scoring_config'    => ['sometimes', 'nullable', 'array'],
        ]);

        $question->update($validated);

        return $this->ok($question->fresh()->load('questionType', 'options'), 'Question updated.');
    }

    public function destroy(Request $request, FormSection $dsamSection, Question $question): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamSection->formTemplate->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $question->delete();

        return $this->noContent('Question deleted.');
    }

    public function reorder(Request $request, FormSection $dsamSection): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamSection->formTemplate->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:dsam_questions,id'],
        ]);

        foreach ($validated['order'] as $index => $questionId) {
            Question::where('id', $questionId)
                ->where('form_section_id', $dsamSection->id)
                ->update(['order_index' => $index]);
        }

        return $this->ok($dsamSection->allQuestions()->get(), 'Questions reordered.');
    }
}
