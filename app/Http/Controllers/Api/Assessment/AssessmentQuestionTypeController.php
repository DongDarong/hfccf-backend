<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Models\AssessmentQuestionType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentQuestionTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizePreschoolUser($request->user())) {
            return $response;
        }

        $types = AssessmentQuestionType::orderBy('label')->get([
            'id',
            'key',
            'label',
            'label_kh',
            'renderer',
            'has_options',
            'has_scoring',
            'has_matrix',
            'is_file',
            'settings_schema',
            'is_active',
            'sort_order',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $types,
        ]);
    }

    private function authorizePreschoolUser(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }
        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacherpreschool'], true)) {
            return null;
        }
        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
