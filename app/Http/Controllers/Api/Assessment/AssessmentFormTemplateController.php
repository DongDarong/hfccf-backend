<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\AssessmentFormTemplateResource;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\User;
use App\Services\AssessmentFormService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssessmentFormTemplateController extends Controller
{
    public function __construct(private AssessmentFormService $service) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page'           => ['sometimes', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'module'         => ['sometimes', 'nullable', 'string', 'max:32'],
            'status'         => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_by'        => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc'],
        ]);

        $perPage       = (int) ($validated['per_page'] ?? 20);
        $search        = trim((string) ($validated['search'] ?? ''));
        $module        = trim((string) ($validated['module'] ?? ''));
        $status        = trim((string) ($validated['status'] ?? ''));
        $sortBy        = in_array($validated['sort_by'] ?? '', ['name', 'status', 'created_at'], true)
            ? $validated['sort_by']
            : 'created_at';
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = AssessmentFormTemplate::query()->withTrashed(false)->where('module', 'preschool');

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($module !== '') {
            $query->where('module', $module);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginator = $query->orderBy($sortBy, $sortDirection)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => AssessmentFormTemplateResource::collection($paginator->items()),
            'meta'    => $this->paginationShape($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'name_kh'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'description_kh' => ['sometimes', 'nullable', 'string'],
            'category'    => ['sometimes', 'nullable', 'string', 'max:64'],
            'settings'    => ['sometimes', 'nullable', 'array'],
            'sections'    => ['sometimes', 'nullable', 'array'],
            'sections.*.id' => ['sometimes', 'nullable', 'integer'],
            'sections.*.code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.title_kh' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.description' => ['sometimes', 'nullable', 'string'],
            'sections.*.description_kh' => ['sometimes', 'nullable', 'string'],
            'sections.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            'sections.*.is_repeatable' => ['sometimes', 'boolean'],
            'sections.*.max_repeats' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sections.*.print_visible' => ['sometimes', 'boolean'],
            'sections.*.scoring_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.settings' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.id' => ['sometimes', 'nullable', 'integer'],
            'sections.*.questions.*.code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.question_type_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.answerType' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.answer_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.label' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.*.questions.*.label_kh' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.*.questions.*.help_text' => ['sometimes', 'nullable', 'string'],
            'sections.*.questions.*.help_text_kh' => ['sometimes', 'nullable', 'string'],
            'sections.*.questions.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.placeholder_kh' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            'sections.*.questions.*.is_required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.is_scored' => ['sometimes', 'boolean'],
            'sections.*.questions.*.max_score' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.questions.*.scoring_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.questions.*.print_visible' => ['sometimes', 'boolean'],
            'sections.*.questions.*.validation_rules' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.conditional_logic' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.calculation_formula' => ['sometimes', 'nullable', 'string'],
            'sections.*.questions.*.settings' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.config' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.options' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.options.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.label_kh' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.score_value' => ['sometimes', 'nullable', 'numeric'],
            'sections.*.questions.*.options.*.risk_tag' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.options.*.color_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sections.*.questions.*.options.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            'sections.*.questions.*.options.*.is_other' => ['sometimes', 'boolean'],
            'sections.*.questions.*.options.*.settings' => ['sometimes', 'nullable', 'array'],
        ]);

        $template = AssessmentFormTemplate::create([
            'uuid'       => (string) Str::uuid(),
            'code'       => 'PRESCHOOL-'.Str::upper(Str::random(6)),
            'name'       => $validated['name'],
            'name_kh'    => $validated['name_kh'] ?? null,
            'description'=> $validated['description'] ?? null,
            'description_kh' => $validated['description_kh'] ?? null,
            'category'   => $validated['category'] ?? 'preschool_assessment',
            'module'     => 'preschool',
            'status'     => 'draft',
            'created_by' => $request->user()->id,
            'settings'   => $validated['settings'] ?? null,
        ]);

        if (! empty($validated['sections'])) {
            $this->service->syncTemplateTree($template, $validated['sections'], $request->user());
        }

        return response()->json([
            'success' => true,
            'message' => 'Form template created.',
            'data'    => new AssessmentFormTemplateResource($template->fresh([
                'sections.questions.options',
                'sections.questions.questionType',
                'versions',
            ])),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'data'    => new AssessmentFormTemplateResource($form->load([
                'publishedBy',
                'archivedBy',
                'sections.questions.options',
                'sections.questions.questionType',
                'versions',
                'scoringRules',
                'riskLevels',
                'printTemplates',
            ])),
        ]);
    }

    public function update(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'name_kh'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'description_kh' => ['sometimes', 'nullable', 'string'],
            'category'    => ['sometimes', 'nullable', 'string', 'max:64'],
            'settings'    => ['sometimes', 'nullable', 'array'],
            'sections'    => ['sometimes', 'nullable', 'array'],
            'sections.*.id' => ['sometimes', 'nullable', 'integer'],
            'sections.*.code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.title_kh' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.description' => ['sometimes', 'nullable', 'string'],
            'sections.*.description_kh' => ['sometimes', 'nullable', 'string'],
            'sections.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            'sections.*.is_repeatable' => ['sometimes', 'boolean'],
            'sections.*.max_repeats' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sections.*.print_visible' => ['sometimes', 'boolean'],
            'sections.*.scoring_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.settings' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.id' => ['sometimes', 'nullable', 'integer'],
            'sections.*.questions.*.code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.question_type_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.answerType' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.answer_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.label' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.*.questions.*.label_kh' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.*.questions.*.help_text' => ['sometimes', 'nullable', 'string'],
            'sections.*.questions.*.help_text_kh' => ['sometimes', 'nullable', 'string'],
            'sections.*.questions.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.placeholder_kh' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            'sections.*.questions.*.is_required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.is_scored' => ['sometimes', 'boolean'],
            'sections.*.questions.*.max_score' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.questions.*.scoring_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.questions.*.print_visible' => ['sometimes', 'boolean'],
            'sections.*.questions.*.validation_rules' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.conditional_logic' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.calculation_formula' => ['sometimes', 'nullable', 'string'],
            'sections.*.questions.*.settings' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.config' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.options' => ['sometimes', 'nullable', 'array'],
            'sections.*.questions.*.options.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.label_kh' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.score_value' => ['sometimes', 'nullable', 'numeric'],
            'sections.*.questions.*.options.*.risk_tag' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sections.*.questions.*.options.*.color_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sections.*.questions.*.options.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            'sections.*.questions.*.options.*.is_other' => ['sometimes', 'boolean'],
            'sections.*.questions.*.options.*.settings' => ['sometimes', 'nullable', 'array'],
        ]);

        if ($form->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Published forms cannot be edited. Duplicate or create a new draft version.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($form->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Archived forms cannot be edited. Restore the draft first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! empty($validated['sections'])) {
            $this->service->syncTemplateTree($form, $validated['sections'], $request->user());
        }

        $form->update([
            'name'        => $validated['name'] ?? $form->name,
            'name_kh'     => $validated['name_kh'] ?? $form->name_kh,
            'description' => $validated['description'] ?? $form->description,
            'description_kh' => $validated['description_kh'] ?? $form->description_kh,
            'category'    => $validated['category'] ?? $form->category,
            'settings'    => $validated['settings'] ?? $form->settings,
            'updated_by'  => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form template updated.',
            'data'    => new AssessmentFormTemplateResource($form->fresh([
                'publishedBy',
                'archivedBy',
                'sections.questions.options',
                'sections.questions.questionType',
                'versions',
            ])),
        ]);
    }

    public function destroy(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $form->delete();

        return response()->json(['success' => true, 'message' => 'Form template deleted.', 'data' => null]);
    }

    public function publish(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($form->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Archived forms must be restored before publishing.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $form->sections()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot publish a form without at least one section.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $form->sections()->whereHas('questions')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot publish a form without at least one question.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $version = $this->service->publishForm($form, $request->input('change_summary'));

        return response()->json([
            'success' => true,
            'message' => 'Form published.',
            'data'    => new AssessmentFormTemplateResource($form->fresh([
                'publishedBy',
                'archivedBy',
                'sections.questions.options',
                'sections.questions.questionType',
                'versions',
            ])),
        ]);
    }

    public function duplicate(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $copy = $this->service->duplicateForm($form);

        return response()->json([
            'success' => true,
            'message' => 'Form duplicated.',
            'data'    => new AssessmentFormTemplateResource($copy->load([
                'publishedBy',
                'archivedBy',
                'sections.questions.options',
                'sections.questions.questionType',
                'versions',
            ])),
        ], Response::HTTP_CREATED);
    }

    public function archive(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $form = $this->service->archiveForm($form, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Form archived.',
            'data'    => new AssessmentFormTemplateResource($form->load([
                'publishedBy',
                'archivedBy',
                'sections.questions.options',
                'sections.questions.questionType',
                'versions',
            ])),
        ]);
    }

    public function restore(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $form = $this->service->restoreForm($form, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Form restored.',
            'data'    => new AssessmentFormTemplateResource($form->load([
                'publishedBy',
                'archivedBy',
                'sections.questions.options',
                'sections.questions.questionType',
                'versions',
            ])),
        ]);
    }

    public function versions(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $versions = $form->versions()
            ->orderBy('version_number')
            ->get()
            ->map(fn (AssessmentFormVersion $version) => [
                'id' => $version->id,
                'template_id' => $version->template_id,
                'version_number' => $version->version_number,
                'label' => $version->label,
                'change_summary' => $version->change_summary,
                'published_at' => $version->published_at?->toIso8601String(),
                'published_by' => $version->published_by,
                'is_current' => (bool) $version->is_current,
                'created_at' => $version->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $versions,
        ]);
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

    private function paginationShape($paginator): array
    {
        return [
            'page'       => $paginator->currentPage(),
            'perPage'    => $paginator->perPage(),
            'total'      => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }
}
