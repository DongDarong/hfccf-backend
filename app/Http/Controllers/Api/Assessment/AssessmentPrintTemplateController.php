<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPrintTemplate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentPrintTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'form_template_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $query = AssessmentPrintTemplate::query();
        if (! empty($validated['form_template_id'])) {
            $query->where('form_template_id', $validated['form_template_id']);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('is_default', 'desc')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'form_template_id' => ['required', 'integer', 'exists:assessment_form_templates,id'],
            'name'             => ['required', 'string', 'max:255'],
            'orientation'      => ['sometimes', 'string', 'in:portrait,landscape'],
            'page_size'        => ['sometimes', 'string', 'in:A4,A3,letter'],
            'blocks'           => ['sometimes', 'array'],
            'is_default'       => ['sometimes', 'boolean'],
        ]);

        $template = AssessmentPrintTemplate::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Print template created.',
            'data'    => $template,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, AssessmentPrintTemplate $printTemplate): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json(['success' => true, 'data' => $printTemplate]);
    }

    public function update(Request $request, AssessmentPrintTemplate $printTemplate): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'orientation' => ['sometimes', 'string', 'in:portrait,landscape'],
            'page_size'   => ['sometimes', 'string', 'in:A4,A3,letter'],
            'blocks'      => ['sometimes', 'array'],
            'is_default'  => ['sometimes', 'boolean'],
        ]);

        $printTemplate->update($validated);

        return response()->json(['success' => true, 'message' => 'Print template updated.', 'data' => $printTemplate->fresh()]);
    }

    public function destroy(Request $request, AssessmentPrintTemplate $printTemplate): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $printTemplate->delete();

        return response()->json(['success' => true, 'message' => 'Print template deleted.', 'data' => null]);
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
