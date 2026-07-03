<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Services\PreschoolWorkflowDefinitionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolWorkflowDefinitionController extends Controller
{
    public function index(Request $request, PreschoolWorkflowDefinitionService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow definitions retrieved successfully.',
            'data' => [
                'items' => $service->listActive()->map(fn ($definition) => [
                    'id' => $definition->id,
                    'key' => $definition->key,
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'domain' => $definition->domain,
                    'isActive' => $definition->is_active,
                    'config' => $definition->config ?? [],
                    'steps' => $definition->steps->map(fn ($step) => [
                        'id' => $step->id,
                        'key' => $step->key,
                        'name' => $step->name,
                        'sortOrder' => $step->sort_order,
                        'stepType' => $step->step_type,
                        'assignedRole' => $step->assigned_role,
                        'slaHours' => $step->sla_hours,
                        'config' => $step->config ?? [],
                    ])->values()->all(),
                ])->values()->all(),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
