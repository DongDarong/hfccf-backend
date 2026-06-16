<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\AssessmentFormTemplateResource;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentFormVersion;
use App\Models\AssessmentQuestionType;
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
            'publish_notes' => ['sometimes', 'nullable', 'string'],
            'version_notes' => ['sometimes', 'nullable', 'string'],
            'review_notes' => ['sometimes', 'nullable', 'string'],
            'duplicate_notes' => ['sometimes', 'nullable', 'string'],
            'restore_notes' => ['sometimes', 'nullable', 'string'],
            'duplicated_from_template_id' => ['sometimes', 'nullable', 'integer'],
            'duplicated_from_version' => ['sometimes', 'nullable', 'integer'],
            'restored_from_template_id' => ['sometimes', 'nullable', 'integer'],
            'restored_from_version' => ['sometimes', 'nullable', 'integer'],
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
            'version_notes' => $validated['version_notes'] ?? $validated['publish_notes'] ?? null,
            'review_notes' => $validated['review_notes'] ?? null,
            'duplicated_from_template_id' => $validated['duplicated_from_template_id'] ?? null,
            'duplicated_from_version' => $validated['duplicated_from_version'] ?? null,
            'restored_from_template_id' => $validated['restored_from_template_id'] ?? null,
            'restored_from_version' => $validated['restored_from_version'] ?? null,
        ]);

        if (! empty($validated['sections'])) {
            $this->service->syncTemplateTree($template, $validated['sections'], $request->user());
        }

        return response()->json([
            'success' => true,
            'message' => 'Form template created.',
            'data'    => new AssessmentFormTemplateResource($template->fresh([
                'publishedBy',
                'archivedBy',
                'reviewedBy',
                'duplicatedFromTemplate',
                'restoredFromTemplate',
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
                'reviewedBy',
                'duplicatedFromTemplate',
                'restoredFromTemplate',
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
            'publish_notes' => ['sometimes', 'nullable', 'string'],
            'version_notes' => ['sometimes', 'nullable', 'string'],
            'review_notes' => ['sometimes', 'nullable', 'string'],
            'duplicate_notes' => ['sometimes', 'nullable', 'string'],
            'restore_notes' => ['sometimes', 'nullable', 'string'],
            'duplicated_from_template_id' => ['sometimes', 'nullable', 'integer'],
            'duplicated_from_version' => ['sometimes', 'nullable', 'integer'],
            'restored_from_template_id' => ['sometimes', 'nullable', 'integer'],
            'restored_from_version' => ['sometimes', 'nullable', 'integer'],
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
            'version_notes' => $validated['version_notes'] ?? $validated['publish_notes'] ?? $form->version_notes,
            'review_notes' => $validated['review_notes'] ?? $form->review_notes,
            'duplicated_from_template_id' => $validated['duplicated_from_template_id'] ?? $form->duplicated_from_template_id,
            'duplicated_from_version' => $validated['duplicated_from_version'] ?? $form->duplicated_from_version,
            'restored_from_template_id' => $validated['restored_from_template_id'] ?? $form->restored_from_template_id,
            'restored_from_version' => $validated['restored_from_version'] ?? $form->restored_from_version,
            'updated_by'  => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form template updated.',
            'data'    => new AssessmentFormTemplateResource($form->fresh([
                'publishedBy',
                'archivedBy',
                'reviewedBy',
                'duplicatedFromTemplate',
                'restoredFromTemplate',
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

        $validated = $request->validate([
            'publish_notes' => ['sometimes', 'nullable', 'string'],
            'version_notes' => ['sometimes', 'nullable', 'string'],
            'review_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $version = $this->service->publishForm($form, [
            'publish_notes' => $validated['publish_notes'] ?? $request->input('change_summary'),
            'version_notes' => $validated['version_notes'] ?? $form->version_notes,
            'review_notes' => $validated['review_notes'] ?? $form->review_notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form published.',
            'data'    => new AssessmentFormTemplateResource($form->fresh([
                'publishedBy',
                'archivedBy',
                'reviewedBy',
                'duplicatedFromTemplate',
                'restoredFromTemplate',
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

        $validated = $request->validate([
            'duplicate_notes' => ['sometimes', 'nullable', 'string'],
            'version_notes' => ['sometimes', 'nullable', 'string'],
            'review_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $copy = $this->service->duplicateForm($form, [
            'version_notes' => $validated['version_notes'] ?? $validated['duplicate_notes'] ?? $form->version_notes,
            'duplicate_notes' => $validated['duplicate_notes'] ?? null,
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form duplicated.',
            'data'    => new AssessmentFormTemplateResource($copy->load([
                'publishedBy',
                'archivedBy',
                'reviewedBy',
                'duplicatedFromTemplate',
                'restoredFromTemplate',
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
                'reviewedBy',
                'duplicatedFromTemplate',
                'restoredFromTemplate',
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

        $validated = $request->validate([
            'restore_notes' => ['sometimes', 'nullable', 'string'],
            'version_notes' => ['sometimes', 'nullable', 'string'],
            'review_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $form = $this->service->restoreForm($form, $request->user(), [
            'restored_from_template_id' => $form->id,
            'restored_from_version' => $form->current_version,
            'version_notes' => $validated['version_notes'] ?? $validated['restore_notes'] ?? $form->version_notes,
            'restore_notes' => $validated['restore_notes'] ?? null,
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form restored.',
            'data'    => new AssessmentFormTemplateResource($form->load([
                'publishedBy',
                'archivedBy',
                'reviewedBy',
                'duplicatedFromTemplate',
                'restoredFromTemplate',
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

        $form->loadMissing(['creator', 'updater', 'publishedBy', 'archivedBy', 'reviewedBy']);
        $questionTypes = AssessmentQuestionType::query()
            ->get(['id', 'key', 'label'])
            ->keyBy('id');

        $versions = $form->versions()
            ->orderBy('version_number')
            ->get()
            ->map(fn (AssessmentFormVersion $version) => $this->shapeVersionResponse($form, $version, $questionTypes));

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

    /**
     * Shape version data for the Preschool builder review UI.
     *
     * The version table is immutable, so we derive counts and current-template
     * lifecycle fields here instead of adding a second versioning model.
     */
    private function shapeVersionResponse(
        AssessmentFormTemplate $form,
        AssessmentFormVersion $version,
        $questionTypes
    ): array {
        $snapshot = $version->snapshot ?? [];
        $sections = data_get($snapshot, 'sections', []);
        $templateSnapshot = data_get($snapshot, 'template', []);
        $sectionsCount = is_array($sections) ? count($sections) : 0;
        $questionsCount = 0;

        $normalizedSections = collect($sections)->map(function ($section) use (&$questionsCount, $questionTypes) {
            $questions = collect(data_get($section, 'questions', []))->map(function ($question) use ($questionTypes) {
                $questionTypeId = (int) data_get($question, 'question_type_id', 0);
                $questionType = $questionTypes->get($questionTypeId);

                return [
                    'id' => data_get($question, 'id'),
                    'code' => data_get($question, 'code'),
                    'label' => data_get($question, 'label'),
                    'label_kh' => data_get($question, 'label_kh'),
                    'question_type_id' => $questionTypeId ?: null,
                    'question_type_key' => $questionType?->key ?? data_get($question, 'question_type_key'),
                    'question_type_label' => $questionType?->label ?? data_get($question, 'question_type_label'),
                    'sort_order' => data_get($question, 'sort_order'),
                    'is_required' => (bool) data_get($question, 'is_required', false),
                    'is_scored' => (bool) data_get($question, 'is_scored', false),
                    'max_score' => data_get($question, 'max_score'),
                    'scoring_weight' => data_get($question, 'scoring_weight'),
                    'validation_rules' => data_get($question, 'validation_rules', []),
                    'conditional_logic' => data_get($question, 'conditional_logic', []),
                    'options' => collect(data_get($question, 'options', []))->map(fn ($option) => [
                        'id' => data_get($option, 'id'),
                        'label' => data_get($option, 'label'),
                        'label_kh' => data_get($option, 'label_kh'),
                        'value' => data_get($option, 'value'),
                        'score_value' => data_get($option, 'score_value'),
                        'sort_order' => data_get($option, 'sort_order'),
                        'is_other' => (bool) data_get($option, 'is_other', false),
                        'settings' => data_get($option, 'settings', []),
                    ])->values(),
                ];
            })->values();

            $questionsCount += $questions->count();

            return [
                'id' => data_get($section, 'id'),
                'code' => data_get($section, 'code'),
                'title' => data_get($section, 'title'),
                'description' => data_get($section, 'description'),
                'sort_order' => data_get($section, 'sort_order'),
                'settings' => data_get($section, 'settings', []),
                'questions' => $questions,
            ];
        })->values();

        return [
            'id' => $version->id,
            'template_id' => $version->template_id,
            'version_number' => $version->version_number,
            'label' => $version->label,
            // Version rows represent published history snapshots. A published_at
            // timestamp is the canonical signal for the version lifecycle state;
            // the mutable template status may already have advanced or changed
            // after the snapshot was captured.
            'status' => $version->published_at ? 'published' : data_get($templateSnapshot, 'status', $form->status),
            'publish_notes' => $version->change_summary,
            'version_notes' => data_get($templateSnapshot, 'version_notes'),
            'review_notes' => data_get($templateSnapshot, 'review_notes'),
            'reviewed_by' => [
                'id' => data_get($templateSnapshot, 'reviewed_by'),
                'name' => data_get($templateSnapshot, 'reviewed_by_name'),
            ],
            'reviewed_at' => data_get($templateSnapshot, 'reviewed_at'),
            'duplicated_from_template_id' => data_get($templateSnapshot, 'duplicated_from_template_id'),
            'duplicated_from_version' => data_get($templateSnapshot, 'duplicated_from_version'),
            'restored_from_template_id' => data_get($templateSnapshot, 'restored_from_template_id'),
            'restored_from_version' => data_get($templateSnapshot, 'restored_from_version'),
            'created_by' => [
                'id' => $form->creator?->id ?? data_get($templateSnapshot, 'created_by'),
                'name' => trim(($form->creator?->first_name ?? '').' '.($form->creator?->last_name ?? '')) ?: null,
            ],
            'updated_by' => [
                'id' => $form->updater?->id ?? data_get($templateSnapshot, 'updated_by'),
                'name' => trim(($form->updater?->first_name ?? '').' '.($form->updater?->last_name ?? '')) ?: null,
            ],
            'published_by' => [
                'id' => $form->publishedBy?->id ?? $version->published_by,
                'name' => trim(($form->publishedBy?->first_name ?? '').' '.($form->publishedBy?->last_name ?? '')) ?: null,
            ],
            'published_at' => $version->published_at?->toIso8601String(),
            'archived_by' => [
                'id' => $form->archivedBy?->id ?? $form->archived_by,
                'name' => trim(($form->archivedBy?->first_name ?? '').' '.($form->archivedBy?->last_name ?? '')) ?: null,
            ],
            'archived_at' => $form->archived_at?->toIso8601String(),
            'sections_count' => $sectionsCount,
            'questions_count' => $questionsCount,
            'change_summary' => $version->change_summary,
            'created_at' => $version->created_at?->toIso8601String(),
            'updated_at' => ($version->published_at?->toIso8601String()) ?? $version->created_at?->toIso8601String(),
            'is_current' => (bool) $version->is_current,
            'snapshot' => [
                'template' => [
                    'id' => data_get($templateSnapshot, 'id'),
                    'uuid' => data_get($templateSnapshot, 'uuid'),
                    'code' => data_get($templateSnapshot, 'code'),
                    'name' => data_get($templateSnapshot, 'name'),
                    'module' => data_get($templateSnapshot, 'module'),
                    'status' => data_get($templateSnapshot, 'status', $form->status),
                    'settings' => data_get($templateSnapshot, 'settings', []),
                    'version' => data_get($templateSnapshot, 'version'),
                    'created_by' => data_get($templateSnapshot, 'created_by', $form->created_by),
                    'updated_by' => data_get($templateSnapshot, 'updated_by', $form->updated_by),
                    'published_at' => data_get($templateSnapshot, 'published_at'),
                    'published_by' => data_get($templateSnapshot, 'published_by'),
                    'archived_at' => data_get($templateSnapshot, 'archived_at'),
                    'archived_by' => data_get($templateSnapshot, 'archived_by'),
                ],
                'sections' => $normalizedSections,
                'sections_count' => $sectionsCount,
                'questions_count' => $questionsCount,
            ],
        ];
    }
}
