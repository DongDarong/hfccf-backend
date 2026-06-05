<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dsam\FormTemplateResource;
use App\Models\Dsam\FormTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormTemplateController extends Controller
{
    private const ADMIN_ROLES  = ['superadmin', 'adminpreschool'];
    private const VIEWER_ROLES = ['superadmin', 'adminpreschool', 'teacherpreschool', 'evaluator'];

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::VIEWER_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'search'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'status'           => ['sometimes', 'nullable', 'in:draft,published,archived'],
            'category'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer'],
            'per_page'         => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user  = $request->user();
        $query = FormTemplate::query()
            ->whereNull('parent_template_id')   // show only root templates; versions listed separately
            ->with(['academicYear', 'createdBy']);

        if ($user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }
        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%'.$validated['search'].'%');
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        if (! empty($validated['academic_year_id'])) {
            $query->where('academic_year_id', $validated['academic_year_id']);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($validated['per_page'] ?? 20);

        return $this->ok(FormTemplateResource::collection($paginator->items()), null, $this->paginationMeta($paginator));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'name_kh'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'description_kh'   => ['sometimes', 'nullable', 'string'],
            'category'         => ['sometimes', 'in:annual_assessment,intake,follow_up,special'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'scoring_config'   => ['sometimes', 'nullable', 'array'],
            'risk_config'      => ['sometimes', 'nullable', 'array'],
            'settings'         => ['sometimes', 'nullable', 'array'],
        ]);

        $template = FormTemplate::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
            'status'          => 'draft',
            'version_number'  => 1,
        ]);

        return $this->created(
            new FormTemplateResource($template->load(['academicYear', 'sections'])),
            'Form template created.',
        );
    }

    // ── Read (full tree) ──────────────────────────────────────────────────────

    public function show(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::VIEWER_ROLES)) {
            return $guard;
        }

        $dsamForm->load([
            'academicYear',
            'createdBy',
            'publishedBy',
            'sections.allQuestions.questionType',
            'sections.allQuestions.options',
        ]);

        return $this->ok(new FormTemplateResource($dsamForm));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamForm->isPublished()) {
            return $this->error('Published forms cannot be edited. Create a new version instead.');
        }

        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'name_kh'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'description_kh'   => ['sometimes', 'nullable', 'string'],
            'category'         => ['sometimes', 'in:annual_assessment,intake,follow_up,special'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'scoring_config'   => ['sometimes', 'nullable', 'array'],
            'risk_config'      => ['sometimes', 'nullable', 'array'],
            'settings'         => ['sometimes', 'nullable', 'array'],
            'version_notes'    => ['sometimes', 'nullable', 'string'],
        ]);

        $dsamForm->update($validated);

        return $this->ok(new FormTemplateResource($dsamForm->fresh()->load('academicYear')), 'Form template updated.');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamForm->isPublished() && $dsamForm->submissions()->exists()) {
            return $this->error('Cannot delete a published form that has submissions.');
        }

        $dsamForm->delete();

        return $this->noContent('Form template deleted.');
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    public function publish(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamForm->isPublished()) {
            return $this->error('Form is already published.');
        }

        if (! $dsamForm->sections()->exists()) {
            return $this->error('Cannot publish a form with no sections.');
        }

        $dsamForm->update([
            'status'       => 'published',
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        return $this->ok(new FormTemplateResource($dsamForm->fresh()), 'Form published.');
    }

    // ── Duplicate ─────────────────────────────────────────────────────────────

    public function duplicate(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $copy = $this->deepCopy($dsamForm, [
            'name'           => $dsamForm->name.' (Copy)',
            'status'         => 'draft',
            'published_at'   => null,
            'published_by'   => null,
            'created_by'     => $request->user()->id,
            'version_number' => 1,
            'parent_template_id' => null,
        ]);

        return $this->created(new FormTemplateResource($copy->load('sections')), 'Form duplicated.');
    }

    // ── New version ───────────────────────────────────────────────────────────

    public function newVersion(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'version_notes'    => ['sometimes', 'nullable', 'string'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
        ]);

        $copy = $this->deepCopy($dsamForm, [
            'status'             => 'draft',
            'published_at'       => null,
            'published_by'       => null,
            'created_by'         => $request->user()->id,
            'parent_template_id' => $dsamForm->id,
            'version_number'     => $dsamForm->version_number + 1,
            'version_notes'      => $validated['version_notes'] ?? null,
            'academic_year_id'   => $validated['academic_year_id'] ?? $dsamForm->academic_year_id,
        ]);

        return $this->created(new FormTemplateResource($copy->load('sections')), 'New version created.');
    }

    // ── Version history ───────────────────────────────────────────────────────

    public function versions(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::VIEWER_ROLES)) {
            return $guard;
        }

        // Walk up to the root template, then fetch all versions in the chain
        $root = $dsamForm;
        while ($root->parent_template_id) {
            $root = $root->parentTemplate;
        }

        $versions = FormTemplate::query()
            ->where(fn ($q) => $q
                ->where('id', $root->id)
                ->orWhere('parent_template_id', $root->id)
            )
            ->withCount('submissions')
            ->orderBy('version_number')
            ->get(['id', 'name', 'status', 'version_number', 'version_notes', 'academic_year_id', 'published_at', 'created_at']);

        return $this->ok($versions);
    }

    // ── Deep copy helper ──────────────────────────────────────────────────────

    private function deepCopy(FormTemplate $source, array $overrides): FormTemplate
    {
        return DB::transaction(function () use ($source, $overrides): FormTemplate {
            $source->load('sections.allQuestions.options');

            $copy = $source->replicate()->fill($overrides);
            $copy->save();

            foreach ($source->sections as $section) {
                $newSection = $section->replicate(['id']);
                $newSection->form_template_id = $copy->id;
                $newSection->save();

                // Map old question id → new question id for conditional re-linking
                $questionMap = [];

                foreach ($section->allQuestions as $question) {
                    $newQ = $question->replicate(['id', 'uuid', 'trigger_option_id']);
                    $newQ->form_section_id    = $newSection->id;
                    $newQ->parent_question_id = null; // will re-link below
                    $newQ->save();

                    $questionMap[$question->id] = $newQ->id;

                    foreach ($question->options as $option) {
                        $newOpt = $option->replicate(['id']);
                        $newOpt->question_id = $newQ->id;
                        $newOpt->save();
                    }
                }

                // Re-link conditional parent_question_id references
                foreach ($section->allQuestions->where('parent_question_id', '!=', null) as $childQ) {
                    if (isset($questionMap[$childQ->id], $questionMap[$childQ->parent_question_id])) {
                        \App\Models\Dsam\Question::find($questionMap[$childQ->id])
                            ?->update(['parent_question_id' => $questionMap[$childQ->parent_question_id]]);
                    }
                }
            }

            return $copy;
        });
    }
}
