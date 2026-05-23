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
                'scoring_rules' => $form->scoringRules()->get(),
                'risk_levels'   => $form->riskLevels()->orderBy('min_score')->get(),
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
            'risk_levels'                 => ['sometimes', 'array'],
            'risk_levels.*.level_name'    => ['required_with:risk_levels', 'string', 'max:64'],
            'risk_levels.*.min_score'     => ['required_with:risk_levels', 'numeric'],
            'risk_levels.*.max_score'     => ['required_with:risk_levels', 'numeric'],
            'risk_levels.*.color'         => ['sometimes', 'nullable', 'string', 'max:16'],
            'risk_levels.*.description'   => ['sometimes', 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($form, $validated) {
            if (isset($validated['scoring_rules'])) {
                $form->scoringRules()->delete();
                foreach ($validated['scoring_rules'] as $rule) {
                    $form->scoringRules()->create($rule);
                }
            }
            if (isset($validated['risk_levels'])) {
                $form->riskLevels()->delete();
                foreach ($validated['risk_levels'] as $level) {
                    $form->riskLevels()->create($level);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Scoring configuration updated.',
            'data'    => [
                'scoring_rules' => $form->scoringRules()->get(),
                'risk_levels'   => $form->riskLevels()->orderBy('min_score')->get(),
            ],
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
