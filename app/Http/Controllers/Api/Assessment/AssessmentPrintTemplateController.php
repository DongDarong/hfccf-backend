<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPrintTemplate;
use App\Models\AssessmentSubmission;
use App\Models\User;
use App\Services\AssessmentLifecycleService;
use App\Services\AssessmentPrintRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssessmentPrintTemplateController extends Controller
{
    public function __construct(private AssessmentLifecycleService $lifecycle) {}

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
            'format'           => ['sometimes', 'string', 'in:pdf,excel,html'],
            'styles'           => ['sometimes', 'nullable', 'string'],
        ]);

        $template = AssessmentPrintTemplate::create([
            'uuid'            => (string) Str::uuid(),
            'form_template_id'=> $validated['form_template_id'],
            'name'            => $validated['name'],
            'orientation'     => $validated['orientation'] ?? 'portrait',
            'page_size'       => $validated['page_size'] ?? 'A4',
            'blocks'          => $validated['blocks'] ?? [],
            'is_default'      => $validated['is_default'] ?? false,
            'format'          => $validated['format'] ?? 'pdf',
            'styles'          => $validated['styles'] ?? null,
            'created_by'      => $request->user()->id,
        ]);

        $this->lifecycle->recordAudit(
            entityType: AssessmentPrintTemplate::class,
            entityId: $template->id,
            action: 'print_template.created',
            entityLabel: $template->name,
            newValue: [
                'form_template_id' => $template->form_template_id,
                'format'           => $template->format,
                'page_size'        => $template->page_size,
                'orientation'      => $template->orientation,
            ],
        );

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

    public function preview(Request $request, AssessmentPrintRenderService $renderer): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'template_id'   => ['sometimes', 'nullable', 'integer', 'exists:assessment_print_templates,id'],
            'submission_id' => ['sometimes', 'nullable', 'integer', 'exists:assessment_submissions,id'],
            'template'      => ['sometimes', 'array'],
            'preview_data'   => ['sometimes', 'array'],
        ]);

        $submission = null;
        if (! empty($validated['submission_id'])) {
            $submission = AssessmentSubmission::query()->findOrFail($validated['submission_id']);
        }

        if (! empty($validated['template'])) {
            $preview = $renderer->renderPreviewHtml(
                $validated['template'],
                $validated['preview_data'] ?? []
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'source'    => 'template',
                    'html'      => $preview['html'],
                    'context'   => $preview['context'],
                    'template'  => $preview['template'],
                    'submission'=> null,
                ],
            ]);
        }

        if (! empty($validated['template_id']) && $submission) {
            $template = AssessmentPrintTemplate::query()->findOrFail($validated['template_id']);

            return response()->json([
                'success' => true,
                'data'    => [
                    'source'    => 'submission',
                    'html'      => $renderer->renderHtml($submission, $template),
                    'context'   => $renderer->buildContext($submission, $template),
                    'template'  => $template,
                    'submission'=> $submission,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Provide either a template payload or a submission with template_id.',
            'data'    => null,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
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
            'format'     => ['sometimes', 'string', 'in:pdf,excel,html'],
            'styles'     => ['sometimes', 'nullable', 'string'],
        ]);

        $printTemplate->update([
            'name'        => $validated['name'] ?? $printTemplate->name,
            'orientation' => $validated['orientation'] ?? $printTemplate->orientation,
            'page_size'   => $validated['page_size'] ?? $printTemplate->page_size,
            'blocks'      => $validated['blocks'] ?? $printTemplate->blocks,
            'is_default'  => $validated['is_default'] ?? $printTemplate->is_default,
            'format'      => $validated['format'] ?? $printTemplate->format,
            'styles'      => array_key_exists('styles', $validated) ? $validated['styles'] : $printTemplate->styles,
            'updated_by'  => $request->user()->id,
        ]);

        $this->lifecycle->recordAudit(
            entityType: AssessmentPrintTemplate::class,
            entityId: $printTemplate->id,
            action: 'print_template.updated',
            entityLabel: $printTemplate->name,
            newValue: [
                'form_template_id' => $printTemplate->form_template_id,
                'format'           => $printTemplate->format,
                'page_size'        => $printTemplate->page_size,
                'orientation'      => $printTemplate->orientation,
            ],
        );

        return response()->json(['success' => true, 'message' => 'Print template updated.', 'data' => $printTemplate->fresh()]);
    }

    public function destroy(Request $request, AssessmentPrintTemplate $printTemplate): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $this->lifecycle->recordAudit(
            entityType: AssessmentPrintTemplate::class,
            entityId: $printTemplate->id,
            action: 'print_template.deleted',
            entityLabel: $printTemplate->name,
            oldValue: [
                'form_template_id' => $printTemplate->form_template_id,
                'format'           => $printTemplate->format,
                'page_size'        => $printTemplate->page_size,
                'orientation'      => $printTemplate->orientation,
            ],
        );

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
