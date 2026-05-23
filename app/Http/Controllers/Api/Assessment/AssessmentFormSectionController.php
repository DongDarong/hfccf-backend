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

        $sections = $form->sections()->orderBy('order')->get();

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

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'order'       => ['sometimes', 'integer', 'min:1'],
            'parent_id'   => ['sometimes', 'nullable', 'integer'],
        ]);

        $section = $form->sections()->create([
            ...$validated,
            'order' => $validated['order'] ?? ($form->sections()->max('order') + 1),
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

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'order'       => ['sometimes', 'integer', 'min:1'],
        ]);

        $section->update($validated);

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

        $section->delete();

        return response()->json(['success' => true, 'message' => 'Section deleted.', 'data' => null]);
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
}
