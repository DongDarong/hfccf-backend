<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\PreschoolHealthAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentHealthAuditController extends Controller
{
    public function __construct(
        private readonly PreschoolHealthAuditService $auditService,
    ) {
    }

    public function index(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'action' => ['sometimes', 'nullable', 'string', 'max:64'],
            'severity' => ['sometimes', 'nullable', 'string', 'max:32'],
            'visibility' => ['sometimes', 'nullable', 'string', 'max:16'],
        ]);

        $paginator = $this->auditService->timeline($student, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool health audit timeline retrieved successfully.',
            'data' => [
                'items' => collect($paginator->items())->map(static function ($item): array {
                    return [
                        'id' => $item->id,
                        'studentId' => $item->student_id,
                        'actorUserId' => $item->actor_user_id,
                        'action' => $item->action,
                        'entityType' => $item->entity_type,
                        'entityId' => $item->entity_id,
                        'severity' => $item->severity,
                        'visibility' => $item->visibility,
                        'beforeState' => $item->before_state,
                        'afterState' => $item->after_state,
                        'message' => $item->message,
                        'createdAt' => optional($item->created_at)?->toISOString(),
                        'actor' => $item->actor ? [
                            'id' => $item->actor->id,
                            'name' => $item->actor->name,
                            'roleCode' => $item->actor->role_code,
                        ] : null,
                    ];
                })->all(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user, PreschoolStudent $student): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        if ($user->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($user, $student)) {
            return null;
        }

        return $this->forbidden();
    }

    private function teacherCanAccessStudent(User $user, PreschoolStudent $student): bool
    {
        return PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->whereHas('students', static function (Builder $query) use ($student): void {
                $query->where('preschool_students.id', $student->id);
            })
            ->exists();
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}