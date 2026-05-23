<?php

namespace App\Services;

use App\Models\AssessmentSubmission;
use App\Models\AssessmentScoringRule;
use App\Models\AssessmentRiskLevel;
use App\Models\AssessmentSubmissionScore;

class AssessmentScoringService
{
    public function calculate(AssessmentSubmission $submission): AssessmentSubmissionScore
    {
        $rules = AssessmentScoringRule::where('form_template_id', $submission->form_template_id)->get();
        $answers = $submission->answers()->with('question')->get();

        $totalScore  = 0;
        $maxScore    = 0;
        $breakdown   = [];

        foreach ($rules as $rule) {
            $ruleScore = $this->applyRule($rule, $answers);
            $totalScore += $ruleScore['score'];
            $maxScore   += $ruleScore['max'];
            $breakdown[] = array_merge(['rule_id' => $rule->id], $ruleScore);
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $riskLevel  = $this->resolveRiskLevel($submission->form_template_id, $percentage);

        return AssessmentSubmissionScore::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'total_score'   => $totalScore,
                'max_score'     => $maxScore,
                'percentage'    => $percentage,
                'risk_level_id' => $riskLevel?->id,
                'breakdown'     => $breakdown,
            ],
        );
    }

    private function applyRule(AssessmentScoringRule $rule, $answers): array
    {
        $score = 0;
        $max   = $rule->max_score ?? 0;

        if ($rule->rule_type === 'per_question' && $rule->question_id) {
            $answer = $answers->firstWhere('question_id', $rule->question_id);
            if ($answer) {
                $score = is_numeric($answer->answer_value) ? (float) $answer->answer_value : 0;
                $score = min($score, $max);
            }
        }

        return ['score' => $score, 'max' => $max];
    }

    private function resolveRiskLevel(int $formTemplateId, float $percentage): ?AssessmentRiskLevel
    {
        return AssessmentRiskLevel::where('form_template_id', $formTemplateId)
            ->where('min_score', '<=', $percentage)
            ->where('max_score', '>=', $percentage)
            ->first();
    }
}
