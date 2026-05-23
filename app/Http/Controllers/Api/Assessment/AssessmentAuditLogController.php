<?php

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAuditLog;
use App\Models\User;
use App\Http\Resources\Assessment\AssessmentAuditLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentAuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'action'   => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = AssessmentAuditLog::with('user')->latest();

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => AssessmentAuditLogResource::collection($paginator->items()),
            'meta'    => [
                'page'       => $paginator->currentPage(),
                'perPage'    => $paginator->perPage(),
                'total'      => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
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
