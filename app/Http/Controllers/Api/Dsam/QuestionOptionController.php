<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dsam\QuestionOptionResource;
use App\Models\Dsam\Question;
use App\Models\Dsam\QuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionOptionController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'adminpreschool'];

    public function index(Request $request, Question $dsamQuestion): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        return $this->ok(QuestionOptionResource::collection($dsamQuestion->options));
    }

    public function store(Request $request, Question $dsamQuestion): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamQuestion->section->formTemplate->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'label'      => ['required', 'string', 'max:500'],
            'label_kh'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'value'      => ['required', 'string', 'max:255'],
            'score_value'=> ['sometimes', 'nullable', 'numeric'],
            'is_default' => ['sometimes', 'boolean'],
            'config'     => ['sometimes', 'nullable', 'array'],
        ]);

        $maxOrder = $dsamQuestion->options()->max('order_index') ?? -1;

        $option = $dsamQuestion->options()->create([
            ...$validated,
            'order_index' => $maxOrder + 1,
        ]);

        return $this->created(new QuestionOptionResource($option), 'Option created.');
    }

    public function update(Request $request, Question $dsamQuestion, QuestionOption $option): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamQuestion->section->formTemplate->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'label'      => ['sometimes', 'string', 'max:500'],
            'label_kh'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'value'      => ['sometimes', 'string', 'max:255'],
            'score_value'=> ['sometimes', 'nullable', 'numeric'],
            'is_default' => ['sometimes', 'boolean'],
            'config'     => ['sometimes', 'nullable', 'array'],
        ]);

        $option->update($validated);

        return $this->ok(new QuestionOptionResource($option->fresh()), 'Option updated.');
    }

    public function destroy(Request $request, Question $dsamQuestion, QuestionOption $option): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamQuestion->section->formTemplate->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $option->delete();

        return $this->noContent('Option deleted.');
    }

    public function reorder(Request $request, Question $dsamQuestion): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:dsam_question_options,id'],
        ]);

        foreach ($validated['order'] as $index => $optionId) {
            QuestionOption::where('id', $optionId)
                ->where('question_id', $dsamQuestion->id)
                ->update(['order_index' => $index]);
        }

        return $this->ok(QuestionOptionResource::collection($dsamQuestion->options), 'Options reordered.');
    }
}
