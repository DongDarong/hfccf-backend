<?php

namespace App\Services;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentScoringRule;
use App\Models\AssessmentRiskLevel;
use App\Models\AssessmentSubmissionScore;

class AssessmentScoringService
{
    public function calculate(AssessmentSubmission $submission): AssessmentSubmissionScore
    {
        $rules = AssessmentScoringRule::where('template_id', $submission->template_id)->get();
        $answers = $submission->answers()->with('question', 'question.options')->get();

        $totalScore = 0;
        $maxScore   = 0;

        foreach ($rules as $rule) {
            $ruleScore = $this->applyRule($rule, $answers);
            $totalScore += $ruleScore['score'];
            $maxScore   += $ruleScore['max'];
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $riskLevel  = $this->resolveRiskLevel($submission->template_id, $percentage);
        $weightedScore = $totalScore;

        return AssessmentSubmissionScore::updateOrCreate(
            [
                'submission_id' => $submission->id,
                'scope'         => 'submission',
                'scope_id'      => null,
            ],
            [
                'raw_score'     => $totalScore,
                'max_score'     => $maxScore,
                'weighted_score'=> $weightedScore,
                'percentage'    => $percentage,
                'risk_level_id' => $riskLevel?->id,
            ],
        );
    }

    private function applyRule(AssessmentScoringRule $rule, $answers): array
    {
        $score = 0.0;
        $max   = (float) ($rule->max_score ?? 0);

        if ($rule->scope === 'question' && $rule->scope_id) {
            $answer = $answers->firstWhere('question_id', $rule->scope_id);
            if ($answer instanceof AssessmentAnswer) {
                $score = $this->resolveAnswerScore($answer);
                $score = min($score, $max > 0 ? $max : $score);
            }
        } elseif ($rule->formula) {
            $score = $this->evaluateFormula($rule, $answers);
            $score = min($score, $max > 0 ? $max : $score);
        }

        return ['score' => $score, 'max' => $max];
    }

    private function resolveRiskLevel(int $formTemplateId, float $percentage): ?AssessmentRiskLevel
    {
        return AssessmentRiskLevel::where('template_id', $formTemplateId)
            ->where('min_score', '<=', $percentage)
            ->where('max_score', '>=', $percentage)
            ->first();
    }

    private function resolveAnswerScore(AssessmentAnswer $answer): float
    {
        if ($answer->score_value !== null) {
            return (float) $answer->score_value;
        }

        if (is_numeric($answer->answer_number)) {
            return (float) $answer->answer_number;
        }

        if (is_array($answer->answer_options) && $answer->question?->options) {
            $selectedValues = collect($answer->answer_options);

            return (float) $answer->question->options
                ->whereIn('value', $selectedValues->all())
                ->sum('score_value');
        }

        return 0.0;
    }

    private function evaluateFormula(AssessmentScoringRule $rule, $answers): float
    {
        $settings = $rule->settings ?? [];
        $expression = $rule->formula;

        if (! is_string($expression) || trim($expression) === '') {
            return 0.0;
        }

        foreach ($answers as $answer) {
            $placeholder = '{{question_'.$answer->question_id.'}}';
            $expression = str_replace($placeholder, (string) ($answer->score_value ?? $answer->answer_number ?? 0), $expression);
        }

        if (preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
            try {
                /** @noinspection PhpEvalInspection */
                return (float) eval('return '.$expression.';');
            } catch (\Throwable) {
                return (float) data_get($settings, 'fallback_score', 0);
            }
        }

        return (float) data_get($settings, 'fallback_score', 0);
    }
}
