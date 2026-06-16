<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\AssessmentFormSectionResource;
use App\Models\AssessmentFormSection;
use App\Models\AssessmentFormTemplate;
use App\Models\User;
use App\Services\AssessmentFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentFormSectionController extends Controller
{
    public function __construct(private AssessmentFormService $service) {}

    public function index(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $sections = $form->sections()->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => AssessmentFormSectionResource::collection($sections),
        ]);
    }

    public function store(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($response = $this->ensureEditable($form)) {
            return $response;
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order'  => ['sometimes', 'integer', 'min:1'],
            'order'       => ['sometimes', 'integer', 'min:1'],
            'parent_id'   => ['sometimes', 'nullable', 'integer'],
        ]);

        $section = $form->sections()->create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'parent_id'   => $validated['parent_id'] ?? null,
            'sort_order'  => $validated['sort_order'] ?? $validated['order'] ?? ((int) ($form->sections()->max('sort_order') ?? 0) + 1),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section created.',
            'data'    => new AssessmentFormSectionResource($section),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, AssessmentFormTemplate $form, AssessmentFormSection $section): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($response = $this->ensureEditable($form)) {
            return $response;
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order'  => ['sometimes', 'integer', 'min:1'],
            'order'       => ['sometimes', 'integer', 'min:1'],
        ]);

        $section->update([
            'title'       => $validated['title'] ?? $section->title,
            'description' => $validated['description'] ?? $section->description,
            'sort_order'  => $validated['sort_order'] ?? $validated['order'] ?? $section->sort_order,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section updated.',
            'data'    => new AssessmentFormSectionResource($section->fresh()),
        ]);
    }

    public function destroy(Request $request, AssessmentFormTemplate $form, AssessmentFormSection $section): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($response = $this->ensureEditable($form)) {
            return $response;
        }

        $section->delete();

        return response()->json(['success' => true, 'message' => 'Section deleted.', 'data' => null]);
    }

    public function reorder(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($response = $this->ensureEditable($form)) {
            return $response;
        }

        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->service->reorderSections($form, $validated['ids']);

        return response()->json(['success' => true, 'message' => 'Sections reordered.', 'data' => null]);
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

    private function ensureEditable(AssessmentFormTemplate $form): ?JsonResponse
    {
        if ($form->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Published forms cannot be edited. Duplicate or create a draft copy first.',
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($form->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Archived forms cannot be edited. Restore the draft first.',
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }
}
