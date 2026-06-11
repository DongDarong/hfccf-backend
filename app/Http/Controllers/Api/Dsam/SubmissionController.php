<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dsam\FormSubmissionResource;
use App\Models\Dsam\Answer;
use App\Models\Dsam\FormSubmission;
use App\Models\Dsam\FormTemplate;
use App\Models\Dsam\Question;
use App\Models\Dsam\SubmissionApproval;
use App\Models\Dsam\SubmissionScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    private const EVALUATOR_ROLES = ['superadmin', 'adminpreschool', 'teacherpreschool', 'evaluator'];
    private const APPROVER_ROLES  = ['superadmin', 'adminpreschool'];

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::EVALUATOR_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'search'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'student_id'       => ['sometimes', 'integer'],
            'form_template_id' => ['sometimes', 'integer'],
            'academic_year_id' => ['sometimes', 'integer'],
            'status'           => ['sometimes', 'string', 'in:draft,in_progress,submitted,under_review,approved,rejected,archived'],
            'risk_level'       => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'per_page'         => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'             => ['sometimes', 'integer', 'min:1'],
        ]);

        $user  = $request->user();
        $query = FormSubmission::query()
            ->with(['student', 'formTemplate', 'academicYear', 'submittedBy']);

        // Evaluators see only their own submissions; admins see all
        if (! in_array($user->role_code, self::APPROVER_ROLES, true)) {
            $query->where('submitted_by', $user->id);
        }

        if (! empty($validated['search'])) {
            $term = '%'.$validated['search'].'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('student', fn ($s) => $s->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('student_code', 'like', $term))
                  ->orWhereHas('formTemplate', fn ($f) => $f->where('name', 'like', $term));
            });
        }

        foreach (['student_id', 'form_template_id', 'academic_year_id', 'status', 'risk_level'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        $paginator = $query->orderByDesc('updated_at')->paginate($validated['per_page'] ?? 20);

        return $this->ok(FormSubmissionResource::collection($paginator->items()), null, $this->paginationMeta($paginator));
    }

    // ── Create (start assessment) ─────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::EVALUATOR_ROLES)) {
            return $guard;
        }

        $validated = $request->validate([
            'form_template_id' => ['required', 'integer', 'exists:dsam_form_templates,id'],
            'student_id'       => ['required', 'integer', 'exists:preschool_students,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        $template = FormTemplate::findOrFail($validated['form_template_id']);

        if (! $template->isPublished()) {
            return $this->error('Only published forms can be used for assessments.');
        }

        // Prevent duplicate active submission for the same student+form+year
        $exists = FormSubmission::where('form_template_id', $validated['form_template_id'])
            ->where('student_id', $validated['student_id'])
            ->where('academic_year_id', $validated['academic_year_id'])
            ->whereNotIn('status', ['archived', 'rejected'])
            ->exists();

        if ($exists) {
            return $this->error('An active assessment already exists for this student and form.');
        }

        $submission = FormSubmission::create([
            ...$validated,
            'submitted_by' => $request->user()->id,
            'status'       => 'draft',
        ]);

        return $this->created(
            new FormSubmissionResource($submission->load(['student', 'formTemplate', 'academicYear'])),
            'Assessment started.',
        );
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public function show(Request $request, FormSubmission $dsamSubmission): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::EVALUATOR_ROLES)) {
            return $guard;
        }

        $dsamSubmission->load([
            'student',
            'formTemplate.sections.allQuestions.questionType',
            'formTemplate.sections.allQuestions.options',
            'academicYear',
            'answers',
            'scores.section',
            'approvals.actor',
        ]);

        return $this->ok(new FormSubmissionResource($dsamSubmission));
    }

    // ── Save draft (auto-save) ────────────────────────────────────────────────

    public function update(Request $request, FormSubmission $dsamSubmission): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::EVALUATOR_ROLES)) {
            return $guard;
        }

        if (! $dsamSubmission->canBeEdited()) {
            return $this->error('This submission cannot be edited in its current state.');
        }

        $validated = $request->validate([
            'current_step'         => ['sometimes', 'integer', 'min:0'],
            'draft_data'           => ['sometimes', 'nullable', 'array'],
            'answers'              => ['sometimes', 'array'],
            'answers.*.question_id'=> ['required_with:answers', 'integer', 'exists:dsam_questions,id'],
            'answers.*.text_value' => ['sometimes', 'nullable', 'string'],
            'answers.*.number_value'=> ['sometimes', 'nullable', 'numeric'],
            'answers.*.date_value' => ['sometimes', 'nullable', 'date'],
            'answers.*.json_value' => ['sometimes', 'nullable', 'array'],
            'answers.*.file_path'  => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($dsamSubmission, $validated): void {
            $dsamSubmission->update([
                'status'       => $dsamSubmission->status === 'draft' ? 'in_progress' : $dsamSubmission->status,
                'current_step' => $validated['current_step'] ?? $dsamSubmission->current_step,
                'draft_data'   => $validated['draft_data'] ?? $dsamSubmission->draft_data,
            ]);

            foreach ($validated['answers'] ?? [] as $answerData) {
                Answer::updateOrCreate(
                    ['submission_id' => $dsamSubmission->id, 'question_id' => $answerData['question_id']],
                    array_filter([
                        'text_value'   => $answerData['text_value'] ?? null,
                        'number_value' => $answerData['number_value'] ?? null,
                        'date_value'   => $answerData['date_value'] ?? null,
                        'json_value'   => isset($answerData['json_value']) ? $answerData['json_value'] : null,
                        'file_path'    => $answerData['file_path'] ?? null,
                    ], fn ($v) => $v !== null),
                );
            }
        });

        return $this->ok(
            new FormSubmissionResource($dsamSubmission->fresh()->load('answers', 'scores')),
            'Progress saved.',
        );
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    public function submit(Request $request, FormSubmission $dsamSubmission): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::EVALUATOR_ROLES)) {
            return $guard;
        }

        if (! $dsamSubmission->canBeEdited()) {
            return $this->error('This submission cannot be submitted in its current state.');
        }

        DB::transaction(function () use ($dsamSubmission, $request): void {
            $dsamSubmission->update([
                'status'       => 'submitted',
                'submitted_at' => now(),
                'draft_data'   => null,    // clear auto-save blob
            ]);

            $this->calculateScores($dsamSubmission);

            SubmissionApproval::create([
                'submission_id' => $dsamSubmission->id,
                'action'        => 'submit',
                'actor_id'      => $request->user()->id,
            ]);
        });

        return $this->ok(
            new FormSubmissionResource($dsamSubmission->fresh()->load('scores.section', 'approvals.actor')),
            'Assessment submitted.',
        );
    }

    // ── Approve / Reject ──────────────────────────────────────────────────────

    public function approve(Request $request, FormSubmission $dsamSubmission): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::APPROVER_ROLES)) {
            return $guard;
        }

        if (! in_array($dsamSubmission->status, ['submitted', 'under_review'], true)) {
            return $this->error('Only submitted or under-review assessments can be approved.');
        }

        $validated = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($dsamSubmission, $request, $validated): void {
            $dsamSubmission->update([
                'status'      => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            SubmissionApproval::create([
                'submission_id' => $dsamSubmission->id,
                'action'        => 'approve',
                'actor_id'      => $request->user()->id,
                'notes'         => $validated['notes'] ?? null,
            ]);
        });

        return $this->ok(new FormSubmissionResource($dsamSubmission->fresh()->load('approvals.actor')), 'Assessment approved.');
    }

    public function reject(Request $request, FormSubmission $dsamSubmission): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::APPROVER_ROLES)) {
            return $guard;
        }

        if (! in_array($dsamSubmission->status, ['submitted', 'under_review'], true)) {
            return $this->error('Only submitted or under-review assessments can be rejected.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($dsamSubmission, $request, $validated): void {
            $dsamSubmission->update([
                'status'           => 'rejected',
                'rejection_reason' => $validated['reason'],
            ]);

            SubmissionApproval::create([
                'submission_id' => $dsamSubmission->id,
                'action'        => 'reject',
                'actor_id'      => $request->user()->id,
                'notes'         => $validated['reason'],
            ]);
        });

        return $this->ok(new FormSubmissionResource($dsamSubmission->fresh()->load('approvals.actor')), 'Assessment rejected.');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(Request $request, FormSubmission $dsamSubmission): JsonResponse
    {
        if ($guard = $this->requireRoles($request->user(), self::APPROVER_ROLES)) {
            return $guard;
        }

        if ($dsamSubmission->isApproved()) {
            return $this->error('Approved submissions cannot be deleted.');
        }

        $dsamSubmission->delete();

        return $this->noContent('Submission deleted.');
    }

    // ── Score calculation ─────────────────────────────────────────────────────

    private function calculateScores(FormSubmission $submission): void
    {
        $submission->load(['formTemplate.sections.allQuestions', 'answers']);

        $riskConfig  = $submission->formTemplate->resolvedRiskConfig();
        $totalScore  = 0;
        $totalMax    = 0;

        foreach ($submission->formTemplate->sections as $section) {
            $rawScore = 0;
            $maxScore = 0;

            foreach ($section->allQuestions->where('is_scored', true) as $question) {
                $maxScore += (float) ($question->max_score ?? 0);

                $answer = $submission->answers->firstWhere('question_id', $question->id);
                if (! $answer) {
                    continue;
                }

                $score = $this->scoreAnswer($answer, $question);
                $answer->update(['score_value' => $score]);
                $rawScore += $score;
            }

            $percentage    = $maxScore > 0 ? ($rawScore / $maxScore) * 100 : 0;
            $weight        = (float) $section->scoring_weight;
            $weightedScore = $percentage * $weight;

            SubmissionScore::updateOrCreate(
                ['submission_id' => $submission->id, 'form_section_id' => $section->id],
                [
                    'raw_score'      => $rawScore,
                    'weighted_score' => $weightedScore,
                    'max_score'      => $maxScore,
                    'percentage'     => $percentage,
                ],
            );

            $totalScore += $weightedScore;
            $totalMax   += 100 * $weight;
        }

        $scorePercentage = $totalMax > 0 ? ($totalScore / $totalMax) * 100 : 0;
        $riskLevel       = $this->resolveRiskLevel($scorePercentage, $riskConfig);

        $submission->update([
            'total_score'        => $totalScore,
            'max_possible_score' => $totalMax,
            'score_percentage'   => $scorePercentage,
            'risk_level'         => $riskLevel,
        ]);
    }

    private function scoreAnswer(Answer $answer, Question $question): float
    {
        $config = $question->scoring_config ?? [];
        $type   = $config['type'] ?? 'direct';

        // For option-based questions the score lives on the selected option(s)
        if ($question->hasOptions()) {
            $selectedValues = is_array($answer->json_value)
                ? $answer->json_value
                : array_filter([$answer->text_value]);

            return (float) $question->options
                ->whereIn('value', $selectedValues)
                ->sum('score_value');
        }

        // Number/rating: use the numeric value directly, capped at max_score
        if ($answer->number_value !== null) {
            $raw = (float) $answer->number_value;
            return min($raw, (float) ($question->max_score ?? $raw));
        }

        return 0.0;
    }

    private function resolveRiskLevel(float $percentage, array $riskConfig): string
    {
        $pct        = ($riskConfig['invert_score'] ?? true) ? 100 - $percentage : $percentage;
        $thresholds = $riskConfig['thresholds'] ?? [];

        usort($thresholds, fn ($a, $b) => $b['min'] <=> $a['min']);

        foreach ($thresholds as $threshold) {
            if ($pct >= ($threshold['min'] ?? 0) && $pct <= ($threshold['max'] ?? 100)) {
                return $threshold['level'];
            }
        }

        return 'medium';
    }
}
