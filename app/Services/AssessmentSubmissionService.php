<?php

namespace App\Services;

use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentSubmissionHistory;
use Illuminate\Support\Facades\DB;

class AssessmentSubmissionService
{
    public function __construct(
        private AssessmentScoringService $scoring,
        private AssessmentLifecycleService $lifecycle,
    ) {}

    public function createDraft(array $data): AssessmentSubmission
    {
        return DB::transaction(function () use ($data) {
            $templateId = $data['template_id'] ?? $data['form_template_id'] ?? null;
            $template = AssessmentFormTemplate::with('currentVersion')->findOrFail($templateId);

            $submission = AssessmentSubmission::create([
                'template_id'   => $template->id,
                'version_id'    => $data['version_id'] ?? $template->currentVersion?->id,
                'student_id'    => $data['student_id'],
                'assessor_id'   => auth()->id(),
                'status'        => 'draft',
                'reviewed_at'   => null,
                'approved_at'   => null,
                'rejected_at'   => null,
                'meta'          => $data['meta'] ?? null,
            ]);

            if (!empty($data['answers'])) {
                $this->saveAnswers($submission, $data['answers']);
            }

            $this->logHistory($submission, null, 'draft', 'Draft created');
            $this->lifecycle->recordSubmissionAudit($submission, 'submission.created', null, ['status' => 'draft']);

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
                $this->normalizeAnswerPayload($answer),
            );
        }
    }

    public function submitForReview(AssessmentSubmission $submission): AssessmentSubmission
    {
        return DB::transaction(function () use ($submission) {
            $submission->update([
                'status'       => 'submitted',
                'submitted_at' => now(),
                'reviewed_at'  => null,
            ]);

            $this->scoring->calculate($submission);
            $this->logHistory($submission, 'draft', 'submitted', 'Submitted for review');
            $this->lifecycle->recordSubmissionAudit($submission, 'submission.submitted', ['status' => 'draft'], ['status' => 'submitted']);

            return $submission->fresh();
        });
    }

    public function approve(AssessmentSubmission $submission, ?string $note = null): AssessmentSubmission
    {
        return DB::transaction(function () use ($submission, $note) {
            $submission->update([
                'status'      => 'approved',
                'reviewer_id' => auth()->id(),
                'approver_id' => auth()->id(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'risk_note'   => $note,
            ]);

            $this->logHistory($submission, 'submitted', 'approved', $note ?? 'Approved');
            $this->lifecycle->recordSubmissionAudit($submission, 'submission.approved', ['status' => 'submitted'], ['status' => 'approved', 'note' => $note]);

            return $submission->fresh();
        });
    }

    public function reject(AssessmentSubmission $submission, ?string $note = null): AssessmentSubmission
    {
        return DB::transaction(function () use ($submission, $note) {
            $submission->update([
                'status'         => 'rejected',
                'reviewer_id'    => auth()->id(),
                'reviewed_at'    => now(),
                'rejected_at'    => now(),
                'rejection_note' => $note,
            ]);

            $this->logHistory($submission, 'submitted', 'rejected', $note ?? 'Rejected');
            $this->lifecycle->recordSubmissionAudit($submission, 'submission.rejected', ['status' => 'submitted'], ['status' => 'rejected', 'note' => $note]);

            return $submission->fresh();
        });
    }

    private function logHistory(AssessmentSubmission $submission, ?string $fromStatus, string $toStatus, ?string $note): void
    {
        AssessmentSubmissionHistory::create([
            'submission_id' => $submission->id,
            'from_status'   => $fromStatus,
            'to_status'     => $toStatus,
            'changed_by'    => auth()->id(),
            'note'          => $note,
        ]);
    }

    private function normalizeAnswerPayload(array $answer): array
    {
        $payload = [
            'question_code' => $answer['question_code'] ?? null,
            'score_value'   => $answer['score_value'] ?? null,
            'is_skipped'    => $answer['is_skipped'] ?? false,
        ];

        if (array_key_exists('answer_options', $answer)) {
            $payload['answer_options'] = $answer['answer_options'];
            return $payload;
        }

        if (array_key_exists('answer_matrix', $answer)) {
            $payload['answer_matrix'] = $answer['answer_matrix'];
            return $payload;
        }

        if (array_key_exists('answer_file', $answer)) {
            $payload['answer_file'] = $answer['answer_file'];
            return $payload;
        }

        if (array_key_exists('answer_gps', $answer)) {
            $payload['answer_gps'] = $answer['answer_gps'];
            return $payload;
        }

        $value = $answer['answer_value'] ?? $answer['value'] ?? null;

        if ($value instanceof \DateTimeInterface) {
            $payload['answer_date'] = $value->format('Y-m-d');
        } elseif (is_array($value)) {
            $payload['answer_options'] = $value;
        } elseif (is_numeric($value)) {
            $payload['answer_number'] = $value;
        } elseif ($value !== null) {
            $payload['answer_text'] = is_bool($value) ? ($value ? '1' : '0') : $value;
        }

        return $payload;
    }
}
