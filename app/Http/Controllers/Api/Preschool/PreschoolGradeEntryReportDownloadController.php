<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Services\PreschoolGradeEntryPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolGradeEntryReportDownloadController extends Controller
{
    public function __invoke(Request $request, PreschoolGradeEntryPdfService $service): Response|JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:preschool_academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:preschool_classes,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        try {
            $export = $service->export($validated);
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Grade Entry PDF rendering is temporarily unavailable.',
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
