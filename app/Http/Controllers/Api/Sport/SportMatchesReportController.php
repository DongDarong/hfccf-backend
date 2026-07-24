<?php

namespace App\Http\Controllers\Api\Sport;

use App\Services\Sport\SportMatchesReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SportMatchesReportController extends SportController
{
    public function __construct(private readonly SportMatchesReportService $service) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) return $response;
        return ApiResponse::successResponse('Sport matches report retrieved successfully.', $this->service->report($this->filters($request)));
    }

    public function download(Request $request): Response|JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) return $response;
        try {
            $export = $this->service->exportPdf($this->filters($request));
        } catch (\RuntimeException $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => 'Sport Matches PDF rendering is temporarily unavailable.', 'data' => null], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response($export['content'], Response::HTTP_OK)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="'.$export['filename'].'"');
    }

    public function downloadExcel(Request $request): Response|JsonResponse
    {
        if ($response = $this->authorizeSportAdmin($request->user())) return $response;
        try {
            $export = $this->service->exportExcel($this->filters($request));
        } catch (\RuntimeException $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => 'Sport Matches Excel export is temporarily unavailable.', 'data' => null], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response($export['content'], Response::HTTP_OK)->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', 'attachment; filename="'.$export['filename'].'"');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_divisions,id'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'tournament_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_tournaments,id'],
        ]);
    }
}
