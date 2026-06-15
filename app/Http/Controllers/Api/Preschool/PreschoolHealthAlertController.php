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

class PreschoolHealthAlertController extends Controller
{
    public function __construct(
        private readonly PreschoolHealthAuditService $auditService,
    ) {
    }

    public function alerts(Request $request): JsonResponse
    {
        $studentId = $request->filled('student_id') ? (int) $request->integer('student_id') : null;

        if ($studentId !== null) {
            $student = PreschoolStudent::query()->find($studentId);

            if (! $student) {
                return $this->notFound('Student not found.');
            }

            if ($response = $this->authorizeViewer($request->user(), $student)) {
                return $response;
            }
        } elseif (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alerts retrieved successfully.',
            'data' => $this->auditService->alertSummary($studentId),
        ], Response::HTTP_OK);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool health dashboard summary retrieved successfully.',
            'data' => $this->auditService->dashboardSummary(),
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user, PreschoolStudent $student): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }

        if ($this->isAdmin($user)) {
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

    private function isAdmin(?User $user): bool
    {
        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], Response::HTTP_NOT_FOUND);
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