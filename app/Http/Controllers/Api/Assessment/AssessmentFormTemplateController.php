<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\AssessmentFormTemplateResource;
use App\Models\AssessmentFormTemplate;
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

        $query = AssessmentFormTemplate::query()->withTrashed(false);

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
            'description' => ['sometimes', 'nullable', 'string'],
            'module'      => ['required', 'string', 'max:32'],
            'settings'    => ['sometimes', 'nullable', 'array'],
            'config'      => ['sometimes', 'nullable', 'array'],
        ]);

        $template = AssessmentFormTemplate::create([
            'uuid'       => (string) Str::uuid(),
            'code'       => strtoupper($validated['module']).'-'.Str::upper(Str::random(6)),
            'name'       => $validated['name'],
            'description'=> $validated['description'] ?? null,
            'module'     => $validated['module'],
            'status'     => 'draft',
            'created_by' => $request->user()->id,
            'settings'   => $validated['settings'] ?? $validated['config'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form template created.',
            'data'    => new AssessmentFormTemplateResource($template),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'data'    => new AssessmentFormTemplateResource($form),
        ]);
    }

    public function update(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($form->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Published forms cannot be edited. Duplicate and edit the copy.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'settings'    => ['sometimes', 'nullable', 'array'],
            'config'      => ['sometimes', 'nullable', 'array'],
        ]);

        $form->update([
            'name'        => $validated['name'] ?? $form->name,
            'description' => $validated['description'] ?? $form->description,
            'settings'    => $validated['settings'] ?? $validated['config'] ?? $form->settings,
            'updated_by'  => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Form template updated.',
            'data'    => new AssessmentFormTemplateResource($form->fresh()),
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

        $version = $this->service->publishForm($form);

        return response()->json([
            'success' => true,
            'message' => 'Form published.',
            'data'    => new AssessmentFormTemplateResource($form->fresh()),
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
            'data'    => new AssessmentFormTemplateResource($copy),
        ], Response::HTTP_CREATED);
    }

    public function archive(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $form->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'message' => 'Form archived.',
            'data'    => new AssessmentFormTemplateResource($form->fresh()),
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
