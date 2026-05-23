<?php

namespace App\Services;

use App\Models\AssessmentSubmission;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentSubmissionHistory;
use App\Models\AssessmentAuditLog;
use Illuminate\Support\Facades\DB;

class AssessmentSubmissionService
{
    public function __construct(private AssessmentScoringService $scoring) {}

    public function createDraft(array $data): AssessmentSubmission
    {
        return DB::transaction(function () use ($data) {
            $submission = AssessmentSubmission::create([
                'form_template_id' => $data['form_template_id'],
                'student_id'       => $data['student_id'],
                'assessor_id'      => auth()->id(),
                'status'           => 'draft',
                'module'           => 'preschool',
            ]);

            if (!empty($data['answers'])) {
                $this->saveAnswers($submission, $data['answers']);
            }

            $this->logHistory($submission, 'created', 'Draft created');

            return $submission;
        });
    }

    public function saveAnswers(AssessmentSubmission $submission, array $answers): void
    {
        foreach ($answers as $answer) {
            AssessmentAnswer::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'question_id'   => $answer['question_id'],
                    'repeat_index'  => $answer['repeat_index'] ?? 0,
                ],
                ['answer_value' => $answer['answer_value'] ?? null],
            );
        }
    }

    public function submitForReview(AssessmentSubmission $submission): AssessmentSubmission
    {
        return DB::transaction(function () use ($submission) {
            $submission->update([
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]);

            $this->scoring->calculate($submission);
            $this->logHistory($submission, 'submitted', 'Submitted for review');
            $this->audit($submission, 'submission.submitted');

            return $submission->fresh();
        });
    }

    public function approve(AssessmentSubmission $submission, ?string $note = null): AssessmentSubmission
    {
        return DB::transaction(function () use ($submission, $note) {
            $submission->update([
                'status'      => 'approved',
                'approver_id' => auth()->id(),
                'review_note' => $note,
                'completed_at' => now(),
            ]);

            $this->logHistory($submission, 'approved', $note ?? 'Approved');
            $this->audit($submission, 'submission.approved');

            return $submission->fresh();
        });
    }

    public function reject(AssessmentSubmission $submission, ?string $note = null): AssessmentSubmission
    {
        return DB::transaction(function () use ($submission, $note) {
            $submission->update([
                'status'           => 'rejected',
                'reviewer_id'      => auth()->id(),
                'rejection_reason' => $note,
            ]);

            $this->logHistory($submission, 'rejected', $note ?? 'Rejected');
            $this->audit($submission, 'submission.rejected');

            return $submission->fresh();
        });
    }

    private function logHistory(AssessmentSubmission $submission, string $action, ?string $note): void
    {
        AssessmentSubmissionHistory::create([
            'submission_id' => $submission->id,
            'action'        => $action,
            'actor_id'      => auth()->id(),
            'note'          => $note,
        ]);
    }

    private function audit(AssessmentSubmission $submission, string $event): void
    {
        AssessmentAuditLog::create([
            'event'         => $event,
            'actor_id'      => auth()->id(),
            'subject_type'  => AssessmentSubmission::class,
            'subject_id'    => $submission->id,
            'description'   => "Submission #{$submission->id} status changed to {$submission->status}",
            'module'        => 'preschool',
        ]);
    }
}
