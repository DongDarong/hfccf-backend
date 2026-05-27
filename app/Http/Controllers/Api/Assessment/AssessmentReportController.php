<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAssessmentExportJob;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentExportLog;
use App\Models\User;
use App\Services\AssessmentLifecycleService;
use App\Services\AssessmentExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentReportController extends Controller
{
    public function __construct(
        private AssessmentLifecycleService $lifecycle,
        private AssessmentExportService $exportService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $total      = AssessmentSubmission::count();
        $active     = AssessmentSubmission::whereIn('status', ['draft', 'submitted', 'under_review'])->count();
        $pending    = AssessmentSubmission::where('status', 'submitted')->count();
        $thisMonth  = AssessmentSubmission::where('status', 'approved')
            ->whereMonth('approved_at', now()->month)->whereYear('approved_at', now()->year)->count();
        $averageScore = (float) (AssessmentSubmission::query()
            ->join('assessment_submission_scores', 'assessment_submissions.id', '=', 'assessment_submission_scores.submission_id')
            ->avg('assessment_submission_scores.percentage') ?? 0);
        $completionRate = $total > 0 ? round(($thisMonth / max($total, 1)) * 100, 2) : 0;
        $recentSubs = AssessmentSubmission::with(['template', 'student'])
            ->latest()->limit(5)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'stats' => [
                    'totalForms'         => \App\Models\AssessmentFormTemplate::count(),
                    'activeSubmissions'  => $active,
                    'pendingReview'      => $pending,
                    'completedThisMonth' => $thisMonth,
                    'total_assessments'  => $total,
                    'average_score'     => round($averageScore, 2),
                    'completion_rate'   => $completionRate,
                ],
                'recent_submissions' => $recentSubs,
            ],
        ]);
    }

    public function riskDistribution(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $distribution = DB::table('assessment_submission_scores')
            ->join('assessment_risk_levels', 'assessment_submission_scores.risk_level_id', '=', 'assessment_risk_levels.id')
            ->select('assessment_risk_levels.label as level_name', 'assessment_risk_levels.color_code as color', DB::raw('COUNT(*) as count'))
            ->groupBy('assessment_risk_levels.id', 'assessment_risk_levels.label', 'assessment_risk_levels.color_code')
            ->get()
            ->map(fn ($row) => [
                'level_name' => $row->level_name,
                'color'      => $row->color,
                'count'      => (int) $row->count,
            ]);

        return response()->json(['success' => true, 'data' => $distribution]);
    }

    public function submissionTrend(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $driver = DB::getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', submitted_at)"
            : 'DATE_FORMAT(submitted_at, "%Y-%m")';

        $trend = DB::table('assessment_submissions')
            ->whereNotNull('submitted_at')
            ->selectRaw($monthExpression.' as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return response()->json(['success' => true, 'data' => $trend]);
    }

    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'format' => ['sometimes', 'nullable', 'string', 'in:pdf,xlsx,zip'],
            'queue'  => ['sometimes', 'boolean'],
        ]);

        $format = $validated['format'] ?? 'pdf';
        $storedFormat = $format === 'xlsx' ? 'excel' : $format;

        $exportLog = $this->lifecycle->startExport([
            'export_type' => $storedFormat,
            'scope'       => 'report',
            'meta'        => [
                'report' => 'assessment',
                'format' => $format,
            ],
        ]);

        if ($request->boolean('queue')) {
            GenerateAssessmentExportJob::dispatch($exportLog->id);

            return response()->json([
                'success' => true,
                'message' => 'Export queued.',
                'data'    => [
                    'id'           => $exportLog->id,
                    'uuid'         => $exportLog->uuid,
                    'status'       => $exportLog->status,
                    'export_type'  => $format,
                    'storage_type' => $exportLog->export_type,
                    'download_url' => route('assessment.exports.download', $exportLog),
                    'status_url'   => route('assessment.exports.status', $exportLog),
                ],
            ], Response::HTTP_ACCEPTED);
        }

        $exportLog = $this->exportService->generate($exportLog);

        return $this->downloadExport($exportLog);
    }

    public function exportStatus(AssessmentExportLog $exportLog): JsonResponse
    {
        if ($response = $this->authorizeAdmin(request()->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $exportLog->id,
                'uuid'         => $exportLog->uuid,
                'status'       => $exportLog->status,
                'export_type'  => $exportLog->export_type === 'excel' ? 'xlsx' : $exportLog->export_type,
                'storage_type' => $exportLog->export_type,
                'file_path'    => $exportLog->file_path,
                'file_size'    => $exportLog->file_size,
                'error_message'=> $exportLog->error_message,
                'download_url' => $exportLog->status === 'completed'
                    ? route('assessment.exports.download', $exportLog)
                    : null,
                'created_at'   => $exportLog->created_at?->toIso8601String(),
                'completed_at' => $exportLog->completed_at?->toIso8601String(),
            ],
        ]);
    }

    public function downloadExport(AssessmentExportLog $exportLog): BinaryFileResponse|JsonResponse
    {
        if ($response = $this->authorizeAdmin(request()->user())) {
            return $response;
        }

        if ($exportLog->status !== 'completed' || empty($exportLog->file_path) || ! Storage::disk('local')->exists($exportLog->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Export file is not ready.',
                'data'    => null,
            ], Response::HTTP_CONFLICT);
        }

        $absolutePath = Storage::disk('local')->path($exportLog->file_path);
        $filename = basename($exportLog->file_path);
        $mime = match ($exportLog->export_type) {
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip'  => 'application/zip',
            'html' => 'text/html; charset=UTF-8',
            default => 'application/pdf',
        };

        return response()->download($absolutePath, $filename, [
            'Content-Type' => $mime,
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

}
