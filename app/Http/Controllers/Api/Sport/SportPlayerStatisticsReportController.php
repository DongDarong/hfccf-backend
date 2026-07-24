<?php

namespace App\Http\Controllers\Api\Sport;

use App\Http\Controllers\Controller;
use App\Services\Sport\SportPlayerStatisticsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SportPlayerStatisticsReportController extends Controller
{
    public function __construct(
        private readonly SportPlayerStatisticsReportService $statisticsReportService,
    ) {}

    /**
     * Get player statistics report data as JSON.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'tournament_id' => $request->query('tournament_id') ? (int) $request->query('tournament_id') : null,
                'team_id' => $request->query('team_id') ? (int) $request->query('team_id') : null,
                'division_id' => $request->query('division_id') ? (int) $request->query('division_id') : null,
            ];

            $report = $this->statisticsReportService->report($filters);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate player statistics report: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download player statistics report as PDF.
     */
    public function downloadPdf(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $filters = [
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'tournament_id' => $request->query('tournament_id') ? (int) $request->query('tournament_id') : null,
                'team_id' => $request->query('team_id') ? (int) $request->query('team_id') : null,
                'division_id' => $request->query('division_id') ? (int) $request->query('division_id') : null,
            ];

            $result = $this->statisticsReportService->exportPdf($filters);

            return response()->streamDownload(
                fn () => print $result['content'],
                $result['filename'],
                ['Content-Type' => 'application/pdf']
            );
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export player statistics report as PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download player statistics report as Excel.
     */
    public function downloadExcel(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $filters = [
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'tournament_id' => $request->query('tournament_id') ? (int) $request->query('tournament_id') : null,
                'team_id' => $request->query('team_id') ? (int) $request->query('team_id') : null,
                'division_id' => $request->query('division_id') ? (int) $request->query('division_id') : null,
            ];

            $result = $this->statisticsReportService->exportExcel($filters);

            return response()->streamDownload(
                fn () => print $result['content'],
                $result['filename'],
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export player statistics report as Excel: '.$e->getMessage(),
            ], 500);
        }
    }
}
