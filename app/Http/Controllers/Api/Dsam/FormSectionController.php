<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Models\Dsam\FormSection;
use App\Models\Dsam\FormTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormSectionController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'adminpreschool'];

    public function index(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        return $this->ok(
            $dsamForm->sections()->with('allQuestions.questionType', 'allQuestions.options')->get()
        );
    }

    public function store(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamForm->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'title_kh'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'description_kh' => ['sometimes', 'nullable', 'string'],
            'scoring_weight' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'is_required'    => ['sometimes', 'boolean'],
            'settings'       => ['sometimes', 'nullable', 'array'],
        ]);

        $maxOrder = $dsamForm->sections()->max('order_index') ?? -1;

        $section = $dsamForm->sections()->create([
            ...$validated,
            'order_index' => $maxOrder + 1,
        ]);

        return $this->created($section, 'Section created.');
    }

    public function update(Request $request, FormTemplate $dsamForm, FormSection $section): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamForm->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'title_kh'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'description_kh' => ['sometimes', 'nullable', 'string'],
            'scoring_weight' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'is_required'    => ['sometimes', 'boolean'],
            'settings'       => ['sometimes', 'nullable', 'array'],
        ]);

        $section->update($validated);

        return $this->ok($section->fresh(), 'Section updated.');
    }

    public function destroy(Request $request, FormTemplate $dsamForm, FormSection $section): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamForm->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $section->delete();

        return $this->noContent('Section deleted.');
    }

    public function reorder(Request $request, FormTemplate $dsamForm): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::ADMIN_ROLES)) {
            return $guard;
        }

        if ($dsamForm->isPublished()) {
            return $this->error('Published forms cannot be modified.');
        }

        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:dsam_form_sections,id'],
        ]);

        foreach ($validated['order'] as $index => $sectionId) {
            FormSection::where('id', $sectionId)
                ->where('form_template_id', $dsamForm->id)
                ->update(['order_index' => $index]);
        }

        return $this->ok($dsamForm->sections()->get(), 'Sections reordered.');
    }
}
