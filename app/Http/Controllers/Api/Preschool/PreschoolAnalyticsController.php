<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Support\PreschoolAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAnalyticsController extends Controller
{
    public function __construct(
        private readonly PreschoolAnalyticsService $analyticsService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return $this->respond($request, 'dashboard', fn (array $filters) => $this->analyticsService->dashboard($request->user(), $filters));
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->respond($request, 'attendance', fn (array $filters) => $this->analyticsService->attendance($request->user(), $filters));
    }

    public function sessions(Request $request): JsonResponse
    {
        return $this->respond($request, 'sessions', fn (array $filters) => $this->analyticsService->sessions($request->user(), $filters));
    }

    public function schedules(Request $request): JsonResponse
    {
        return $this->respond($request, 'schedules', fn (array $filters) => $this->analyticsService->schedules($request->user(), $filters));
    }

    public function alerts(Request $request): JsonResponse
    {
        return $this->respond($request, 'alerts', fn (array $filters) => $this->analyticsService->alerts($request->user(), $filters));
    }

    public function students(Request $request): JsonResponse
    {
        return $this->respond($request, 'students', fn (array $filters) => $this->analyticsService->students($request->user(), $filters));
    }

    public function teachers(Request $request): JsonResponse
    {
        return $this->respond($request, 'teachers', fn (array $filters) => $this->analyticsService->teachers($request->user(), $filters));
    }

    public function guardianContacts(Request $request): JsonResponse
    {
        return $this->respond($request, 'guardian-contacts', fn (array $filters) => $this->analyticsService->guardianContacts($request->user(), $filters));
    }

    public function reportAttendance(Request $request): JsonResponse
    {
        return $this->respond($request, 'reports/attendance', fn (array $filters) => $this->analyticsService->reportAttendance($request->user(), $filters));
    }

    public function reportSessions(Request $request): JsonResponse
    {
        return $this->respond($request, 'reports/sessions', fn (array $filters) => $this->analyticsService->reportSessions($request->user(), $filters));
    }

    public function reportSchedules(Request $request): JsonResponse
    {
        return $this->respond($request, 'reports/schedules', fn (array $filters) => $this->analyticsService->reportSchedules($request->user(), $filters));
    }

    private function respond(Request $request, string $scope, callable $callback): JsonResponse
    {
        if ($response = $this->authorizeRead($request)) {
            return $response;
        }

        $filters = $this->validateFilters($request);
        $payload = $callback($filters);

        return response()->json([
            'success' => true,
            'message' => 'Preschool analytics retrieved successfully.',
            'data' => [
                'scope' => $scope,
                ...$payload,
            ],
        ], Response::HTTP_OK);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'teacher_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);
    }

    private function authorizeRead(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
