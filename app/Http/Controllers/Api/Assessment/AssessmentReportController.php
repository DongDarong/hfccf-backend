<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentRiskLevel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AssessmentReportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $total        = AssessmentSubmission::where('module', 'preschool')->count();
        $active       = AssessmentSubmission::where('module', 'preschool')->whereIn('status', ['draft', 'submitted', 'under_review'])->count();
        $pending      = AssessmentSubmission::where('module', 'preschool')->where('status', 'submitted')->count();
        $thisMonth    = AssessmentSubmission::where('module', 'preschool')->where('status', 'approved')
            ->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count();
        $recentSubs   = AssessmentSubmission::with(['formTemplate', 'student'])
            ->where('module', 'preschool')->latest()->limit(5)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'stats' => [
                    'totalForms'         => \App\Models\AssessmentFormTemplate::where('module', 'preschool')->count(),
                    'activeSubmissions'  => $active,
                    'pendingReview'      => $pending,
                    'completedThisMonth' => $thisMonth,
                    'total_assessments'  => $total,
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
            ->select('assessment_risk_levels.level_name', 'assessment_risk_levels.color', DB::raw('COUNT(*) as count'))
            ->groupBy('assessment_risk_levels.id', 'assessment_risk_levels.level_name', 'assessment_risk_levels.color')
            ->get();

        return response()->json(['success' => true, 'data' => $distribution]);
    }

    public function submissionTrend(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $trend = DB::table('assessment_submissions')
            ->where('module', 'preschool')
            ->whereNotNull('submitted_at')
            ->selectRaw('DATE_FORMAT(submitted_at, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return response()->json(['success' => true, 'data' => $trend]);
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
