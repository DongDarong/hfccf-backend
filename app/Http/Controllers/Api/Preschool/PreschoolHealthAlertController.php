<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolClass;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\PreschoolHealthAlertService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PreschoolHealthAlertController extends Controller
{
    public function __construct(
        private readonly PreschoolHealthAlertService $alertService,
    ) {
    }

    public function alerts(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $studentId = $request->filled('student_id') ? (int) $request->integer('student_id') : null;

        if ($studentId !== null) {
            $student = PreschoolStudent::query()->find($studentId);

            if (! $student) {
                return $this->notFound('Student not found.');
            }

            if ($response = $this->authorizeViewer($viewer, $student)) {
                return $response;
            }
        } elseif (! $viewer) {
            return $this->unauthorized();
        }

        $filters = $request->only(['student_id', 'status', 'severity', 'assigned_to', 'alert_type', 'search', 'page', 'per_page']);
        $pagination = $this->alertService->listAlerts($viewer, $filters);
        $summary = $this->alertService->dashboardSummary($viewer, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alerts retrieved successfully.',
            'data' => [
                'summary' => $summary['summary'],
                'items' => $pagination->items(),
                'pagination' => [
                    'current_page' => $pagination->currentPage(),
                    'last_page' => $pagination->lastPage(),
                    'per_page' => $pagination->perPage(),
                    'total' => $pagination->total(),
                ],
                'unresolvedCriticalItems' => $summary['unresolvedCriticalItems'],
            ],
        ], Response::HTTP_OK);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $filters = $request->only(['student_id', 'status', 'severity', 'assigned_to', 'alert_type', 'search']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health dashboard summary retrieved successfully.',
            'data' => $this->alertService->dashboardSummary($request->user(), $filters),
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolHealthAlert $alert): JsonResponse
    {
        if ($response = $this->authorizeAlertViewer($request->user(), $alert)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alert retrieved successfully.',
            'data' => $this->alertService->showAlert($alert, $request->user()),
        ], Response::HTTP_OK);
    }

    public function studentAlerts(Request $request, PreschoolStudent $student): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        $filters = array_merge($request->only(['status', 'severity', 'assigned_to', 'alert_type', 'search', 'page', 'per_page']), [
            'student_id' => $student->id,
        ]);

        $pagination = $this->alertService->listAlerts($request->user(), $filters);
        $summary = $this->alertService->dashboardSummary($request->user(), $filters);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student health alerts retrieved successfully.',
            'data' => [
                'summary' => $summary['summary'],
                'items' => $pagination->items(),
                'pagination' => [
                    'current_page' => $pagination->currentPage(),
                    'last_page' => $pagination->lastPage(),
                    'per_page' => $pagination->perPage(),
                    'total' => $pagination->total(),
                ],
                'unresolvedCriticalItems' => $summary['unresolvedCriticalItems'],
                'alertDetails' => $summary['items'],
            ],
        ], Response::HTTP_OK);
    }

    public function acknowledge(Request $request, PreschoolHealthAlert $alert): JsonResponse
    {
        if ($response = $this->authorizeAlertActor($request->user(), $alert, true)) {
            return $response;
        }

        $updated = $this->alertService->acknowledge($alert, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alert acknowledged successfully.',
            'data' => [
                'alert' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    public function assign(Request $request, PreschoolHealthAlert $alert): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'assigned_to_user_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $assigneeId = isset($data['assigned_to_user_id'])
            ? trim((string) $data['assigned_to_user_id'])
            : null;

        $assignee = $assigneeId !== null && $assigneeId !== ''
            ? User::query()->find($assigneeId)
            : null;

        $updated = $this->alertService->assign($alert, $request->user(), $assignee);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alert assigned successfully.',
            'data' => [
                'alert' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    public function status(Request $request, PreschoolHealthAlert $alert): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'acknowledged', 'in_progress', 'resolved', 'closed'])],
        ]);

        $updated = $this->alertService->changeStatus($alert, $request->user(), $data['status']);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alert status updated successfully.',
            'data' => [
                'alert' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    public function resolve(Request $request, PreschoolHealthAlert $alert): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'resolution_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $updated = $this->alertService->resolve($alert, $request->user(), $data['resolution_notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alert resolved successfully.',
            'data' => [
                'alert' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    public function close(Request $request, PreschoolHealthAlert $alert): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'resolution_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $updated = $this->alertService->close($alert, $request->user(), $data['resolution_notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Preschool health alert closed successfully.',
            'data' => [
                'alert' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAlertViewer(?User $user, PreschoolHealthAlert $alert): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }

        if ($this->isAdmin($user)) {
            return null;
        }

        if ($user->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($user, $alert->student)) {
            return null;
        }

        return $this->forbidden();
    }

    private function authorizeAlertActor(?User $user, PreschoolHealthAlert $alert, bool $allowTeacherAck = false): ?JsonResponse
    {
        if (! $user) {
            return $this->unauthorized();
        }

        if ($this->isAdmin($user)) {
            return null;
        }

        if ($allowTeacherAck && $user->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($user, $alert->student)) {
            return null;
        }

        return $this->forbidden();
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

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $this->isAdmin($user)) {
            return $this->forbidden();
        }

        return null;
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], Response::HTTP_NOT_FOUND);
    }

    protected function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
