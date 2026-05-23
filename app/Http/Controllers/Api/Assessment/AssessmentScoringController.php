<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Models\AssessmentFormTemplate;
use App\Models\AssessmentRiskLevel;
use App\Models\AssessmentScoringRule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssessmentScoringController extends Controller
{
    public function show(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'scoring_rules' => $form->scoringRules()->get()->map(fn (AssessmentScoringRule $rule) => $this->formatRule($rule)),
                'risk_levels'   => $form->riskLevels()->orderBy('min_score')->get()->map(fn (AssessmentRiskLevel $level) => $this->formatRiskLevel($level)),
            ],
        ]);
    }

    public function update(Request $request, AssessmentFormTemplate $form): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'scoring_rules'               => ['sometimes', 'array'],
            'scoring_rules.*.rule_type'   => ['required_with:scoring_rules', 'string', 'max:32'],
            'scoring_rules.*.max_score'   => ['sometimes', 'nullable', 'numeric'],
            'scoring_rules.*.pass_score'  => ['sometimes', 'nullable', 'numeric'],
            'scoring_rules.*.weight'      => ['sometimes', 'nullable', 'numeric'],
            'scoring_rules.*.question_id' => ['sometimes', 'nullable', 'integer'],
            'scoring_rules.*.scope'       => ['sometimes', 'nullable', 'string', 'max:32'],
            'scoring_rules.*.scope_id'    => ['sometimes', 'nullable', 'integer'],
            'scoring_rules.*.formula'     => ['sometimes', 'nullable', 'string'],
            'risk_levels'                 => ['sometimes', 'array'],
            'risk_levels.*.level_name'    => ['sometimes', 'nullable', 'string', 'max:64'],
            'risk_levels.*.label'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'risk_levels.*.min_score'     => ['required_with:risk_levels', 'numeric'],
            'risk_levels.*.max_score'     => ['required_with:risk_levels', 'numeric'],
            'risk_levels.*.color'         => ['sometimes', 'nullable', 'string', 'max:16'],
            'risk_levels.*.color_code'    => ['sometimes', 'nullable', 'string', 'max:16'],
            'risk_levels.*.description'   => ['sometimes', 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($form, $validated) {
            if (isset($validated['scoring_rules'])) {
                $form->scoringRules()->delete();
                foreach ($validated['scoring_rules'] as $rule) {
                    $form->scoringRules()->create([
                        'template_id'   => $form->id,
                        'scope'         => $rule['scope'] ?? (! empty($rule['question_id']) ? 'question' : 'submission'),
                        'scope_id'      => $rule['scope_id'] ?? ($rule['question_id'] ?? null),
                        'rule_type'     => $rule['rule_type'],
                        'formula'       => $rule['formula'] ?? null,
                        'max_score'     => $rule['max_score'] ?? null,
                        'pass_score'    => $rule['pass_score'] ?? null,
                        'settings'      => array_filter([
                            'weight' => $rule['weight'] ?? null,
                        ], static fn ($value) => $value !== null),
                    ]);
                }
            }
            if (isset($validated['risk_levels'])) {
                $form->riskLevels()->delete();
                foreach ($validated['risk_levels'] as $level) {
                    $label = $level['label'] ?? $level['level_name'] ?? 'Risk Level';
                    $form->riskLevels()->create([
                        'template_id'     => $form->id,
                        'label'           => $label,
                        'key'             => Str::slug($label) ?: 'risk-level',
                        'min_score'       => $level['min_score'],
                        'max_score'       => $level['max_score'],
                        'color_code'      => $level['color_code'] ?? $level['color'] ?? null,
                        'description'     => $level['description'] ?? null,
                        'sort_order'      => $level['sort_order'] ?? 0,
                        'recommendations'  => $level['recommendations'] ?? null,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Scoring configuration updated.',
            'data'    => [
                'scoring_rules' => $form->scoringRules()->get()->map(fn (AssessmentScoringRule $rule) => $this->formatRule($rule)),
                'risk_levels'   => $form->riskLevels()->orderBy('min_score')->get()->map(fn (AssessmentRiskLevel $level) => $this->formatRiskLevel($level)),
            ],
        ]);
    }

    private function formatRule(AssessmentScoringRule $rule): array
    {
        return [
            'id'         => $rule->id,
            'template_id'=> $rule->template_id,
            'rule_type'  => $rule->rule_type,
            'scope'      => $rule->scope,
            'scope_id'   => $rule->scope_id,
            'question_id'=> $rule->question_id,
            'formula'    => $rule->formula,
            'max_score'  => $rule->max_score,
            'pass_score' => $rule->pass_score,
            'weight'     => data_get($rule->settings, 'weight'),
            'settings'   => $rule->settings,
        ];
    }

    private function formatRiskLevel(AssessmentRiskLevel $level): array
    {
        return [
            'id'          => $level->id,
            'template_id' => $level->template_id,
            'label'       => $level->label,
            'level_name'   => $level->label,
            'key'         => $level->key,
            'min_score'   => $level->min_score,
            'max_score'   => $level->max_score,
            'color_code'  => $level->color_code,
            'color'       => $level->color_code,
            'description' => $level->description,
            'recommendations' => $level->recommendations,
        ];
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
