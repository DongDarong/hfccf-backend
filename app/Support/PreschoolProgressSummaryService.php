<?php

namespace App\Support;

use App\Models\PreschoolStudent;
use App\Models\User;

final class PreschoolProgressSummaryService
{
    /**
     * Keep progress summary logic separate from the main assessment service so
     * future Preschool reporting can reuse the same summary data safely.
     */
    public function forStudent(User $user, PreschoolStudent $student): array
    {
        $summary = app(PreschoolAssessmentService::class)->progressSummary($user, $student);
        $snapshot = app(PreschoolReportSnapshotService::class)->latestForContext('progress_summary', [
            'student_id' => $student->id,
        ]);

        return [
            ...$summary,
            'source' => $snapshot ? 'snapshot' : 'live',
            'snapshot' => $snapshot ? app(PreschoolReportSnapshotService::class)->snapshotPayload($snapshot) : null,
            'frozen' => (bool) $snapshot,
        ];
    }
}
