<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\AssessmentQuestionResource;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Services\AssessmentFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentQuestionController extends Controller
{
    public function __construct(private AssessmentFormService $service) {}

    public function index(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $questions = AssessmentQuestion::whereHas('section', fn ($q) => $q->where('form_template_id', $form->id))
            ->with(['questionType', 'options'])
            ->orderBy('order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => AssessmentQuestionResource::collection($questions),
        ]);
    }

    public function show(Request $request, AssessmentFormTemplate $form, AssessmentQuestion $question): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $question->load(['questionType', 'options', 'matrixRows']);

        return response()->json([
            'success' => true,
            'data'    => new AssessmentQuestionResource($question),
        ]);
    }

    public function store(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'section_id'         => ['required', 'integer', 'exists:assessment_form_sections,id'],
            'question_type_id'   => ['required', 'integer', 'exists:assessment_question_types,id'],
            'question_text'      => ['required', 'string', 'max:1000'],
            'help_text'          => ['sometimes', 'nullable', 'string'],
            'placeholder'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_required'        => ['sometimes', 'boolean'],
            'order'              => ['sometimes', 'integer', 'min:1'],
            'config'             => ['sometimes', 'nullable', 'array'],
            'conditional_logic'  => ['sometimes', 'nullable', 'array'],
        ]);

        $question = AssessmentQuestion::create([
            ...$validated,
            'order' => $validated['order'] ?? (AssessmentQuestion::where('section_id', $validated['section_id'])->max('order') + 1),
        ]);

        $question->load(['questionType', 'options']);

        return response()->json([
            'success' => true,
            'message' => 'Question created.',
            'data'    => new AssessmentQuestionResource($question),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, AssessmentFormTemplate $form, AssessmentQuestion $question): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'question_text'     => ['sometimes', 'string', 'max:1000'],
            'help_text'         => ['sometimes', 'nullable', 'string'],
            'placeholder'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_required'       => ['sometimes', 'boolean'],
            'order'             => ['sometimes', 'integer', 'min:1'],
            'config'            => ['sometimes', 'nullable', 'array'],
            'conditional_logic' => ['sometimes', 'nullable', 'array'],
        ]);

        $question->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Question updated.',
            'data'    => new AssessmentQuestionResource($question->fresh()->load(['questionType', 'options'])),
        ]);
    }

    public function destroy(Request $request, AssessmentFormTemplate $form, AssessmentQuestion $question): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $question->delete();

        return response()->json(['success' => true, 'message' => 'Question deleted.', 'data' => null]);
    }

    public function duplicate(Request $request, AssessmentFormTemplate $form, AssessmentQuestion $question): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $copy = $question->replicate();
        $copy->order = $question->order + 1;
        $copy->save();

        foreach ($question->options as $option) {
            $newOption = $option->replicate(['question_id']);
            $newOption->question_id = $copy->id;
            $newOption->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Question duplicated.',
            'data'    => new AssessmentQuestionResource($copy->load(['questionType', 'options'])),
        ], Response::HTTP_CREATED);
    }

    public function reorder(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->service->reorderQuestions($form, $validated['ids']);

        return response()->json(['success' => true, 'message' => 'Questions reordered.', 'data' => null]);
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }
        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }
        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }

    private function authorizePreschoolUser(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }
        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacherpreschool'], true)) {
            return null;
        }
        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
