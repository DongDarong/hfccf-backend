<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Services\PreschoolStudentSummaryPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentSummaryReportDownloadController extends Controller
{
    public function __invoke(Request $request, PreschoolStudentSummaryPdfService $service): Response|JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(['individual', 'class'])],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
        ]);

        if ($validated['mode'] === 'individual' && empty($validated['student_id'])) {
            throw ValidationException::withMessages([
                'student_id' => ['The student id field is required when mode is individual.'],
            ]);
        }

        try {
            $export = $service->export($validated);
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Student Summary PDF rendering is temporarily unavailable.',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response($export['content'], Response::HTTP_OK)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$export['filename'].'"');
    }

    private function authorizeAdmin(?object $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
