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

        $questions = AssessmentQuestion::where('template_id', $form->id)
            ->with(['questionType', 'options'])
            ->orderBy('sort_order')
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
            'question_text'      => ['sometimes', 'nullable', 'string', 'max:1000'],
            'label'              => ['sometimes', 'nullable', 'string', 'max:1000'],
            'help_text'          => ['sometimes', 'nullable', 'string'],
            'placeholder'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_required'        => ['sometimes', 'boolean'],
            'sort_order'         => ['sometimes', 'integer', 'min:1'],
            'order'              => ['sometimes', 'integer', 'min:1'],
            'settings'           => ['sometimes', 'nullable', 'array'],
            'config'             => ['sometimes', 'nullable', 'array'],
            'conditional_logic'  => ['sometimes', 'nullable', 'array'],
        ]);

        $question = AssessmentQuestion::create([
            'template_id'       => $form->id,
            'section_id'        => $validated['section_id'],
            'question_type_id'  => $validated['question_type_id'],
            'label'             => $validated['label'] ?? $validated['question_text'] ?? null,
            'help_text'         => $validated['help_text'] ?? null,
            'placeholder'       => $validated['placeholder'] ?? null,
            'is_required'       => $validated['is_required'] ?? false,
            'sort_order'        => $validated['sort_order'] ?? $validated['order'] ?? ((int) (AssessmentQuestion::where('section_id', $validated['section_id'])->max('sort_order') ?? 0) + 1),
            'settings'          => $validated['settings'] ?? $validated['config'] ?? [],
            'conditional_logic' => $validated['conditional_logic'] ?? null,
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
            'label'             => ['sometimes', 'string', 'max:1000'],
            'help_text'         => ['sometimes', 'nullable', 'string'],
            'placeholder'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_required'       => ['sometimes', 'boolean'],
            'sort_order'        => ['sometimes', 'integer', 'min:1'],
            'order'             => ['sometimes', 'integer', 'min:1'],
            'settings'          => ['sometimes', 'nullable', 'array'],
            'config'            => ['sometimes', 'nullable', 'array'],
            'conditional_logic' => ['sometimes', 'nullable', 'array'],
        ]);

        $question->update([
            'label'             => $validated['label'] ?? $validated['question_text'] ?? $question->label,
            'help_text'         => $validated['help_text'] ?? $question->help_text,
            'placeholder'       => $validated['placeholder'] ?? $question->placeholder,
            'is_required'       => $validated['is_required'] ?? $question->is_required,
            'sort_order'        => $validated['sort_order'] ?? $validated['order'] ?? $question->sort_order,
            'settings'          => $validated['settings'] ?? $validated['config'] ?? $question->settings,
            'conditional_logic' => $validated['conditional_logic'] ?? $question->conditional_logic,
        ]);

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
        $copy->sort_order = $question->sort_order + 1;
        $copy->save();

        foreach ($question->options as $option) {
            $newOption = $option->replicate(['question_id']);
            $newOption->question_id = $copy->id;
            $newOption->save();
        }

        foreach ($question->matrixRows as $row) {
            $newRow = $row->replicate(['question_id']);
            $newRow->question_id = $copy->id;
            $newRow->save();
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
