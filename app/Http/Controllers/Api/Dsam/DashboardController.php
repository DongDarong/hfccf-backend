<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Models\Dsam\FormSubmission;
use App\Models\PreschoolStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        $validated = $request->validate([
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
        ]);

        $yearId = $validated['academic_year_id'] ?? null;

        $submissionQuery = FormSubmission::query();
        if ($yearId) {
            $submissionQuery->where('academic_year_id', $yearId);
        }

        // KPI counts
        $totalStudents = PreschoolStudent::where('status', 'active')->count();
        $totalAssessed = (clone $submissionQuery)->where('status', 'approved')->distinct('student_id')->count('student_id');
        $pendingReview = (clone $submissionQuery)->whereIn('status', ['submitted', 'under_review'])->count();

        // Risk distribution (approved only)
        $riskDistribution = (clone $submissionQuery)
            ->where('status', 'approved')
            ->whereNotNull('risk_level')
            ->select('risk_level', DB::raw('count(*) as total'))
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        // Submission status breakdown
        $statusBreakdown = (clone $submissionQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Critical risk students (most recent submission per student)
        $criticalStudents = FormSubmission::query()
            ->with('student:id,first_name,last_name,student_code')
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->where('status', 'approved')
            ->where('risk_level', 'critical')
            ->latest('approved_at')
            ->limit(10)
            ->get(['id', 'uuid', 'student_id', 'risk_level', 'score_percentage', 'approved_at']);

        // Recent submissions
        $recentSubmissions = FormSubmission::query()
            ->with(['student:id,first_name,last_name,student_code', 'submittedBy:id,first_name,last_name'])
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->latest('updated_at')
            ->limit(10)
            ->get(['id', 'uuid', 'student_id', 'status', 'risk_level', 'score_percentage', 'submitted_by', 'updated_at']);

        return $this->ok([
            'kpi' => [
                'total_students'    => $totalStudents,
                'total_assessed'    => $totalAssessed,
                'assessment_rate'   => $totalStudents > 0 ? round($totalAssessed / $totalStudents * 100, 1) : 0,
                'pending_review'    => $pendingReview,
            ],
            'risk_distribution'  => $riskDistribution,
            'status_breakdown'   => $statusBreakdown,
            'critical_students'  => $criticalStudents,
            'recent_submissions' => $recentSubmissions,
        ]);
    }
}
