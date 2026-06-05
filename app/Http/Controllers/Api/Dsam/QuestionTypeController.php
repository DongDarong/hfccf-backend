<?php

namespace App\Http\Controllers\Api\Dsam;

use App\Http\Controllers\Controller;
use App\Models\Dsam\QuestionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($guard = $this->requireAuth($request->user())) {
            return $guard;
        }

        $types = QuestionType::active()->get([
            'id', 'name', 'display_name', 'display_name_kh',
            'icon', 'has_options', 'has_scoring', 'config_schema', 'sort_order',
        ]);

        return $this->ok($types);
    }
}
