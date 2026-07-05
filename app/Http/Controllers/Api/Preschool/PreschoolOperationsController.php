<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Support\PreschoolOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolOperationsController extends Controller
{
    public function dashboard(Request $request, PreschoolOperationsService $service): JsonResponse
    {
        if ($response = $this->authorizeRead($request->user())) {
            return $response;
        }

        $validated = $this->validateFilters($request);
        $payload = $service->dashboard($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Preschool operations dashboard retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'academicYearId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'termId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'dateFrom' => ['sometimes', 'nullable', 'date'],
            'dateTo' => ['sometimes', 'nullable', 'date'],
            'classId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'studentId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'teacherUserId' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);
    }

    private function authorizeRead(?object $user): ?JsonResponse
    {
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
