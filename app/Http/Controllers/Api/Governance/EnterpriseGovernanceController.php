<?php

namespace App\Http\Controllers\Api\Governance;

use App\Http\Controllers\Controller;
use App\Support\EnterpriseGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnterpriseGovernanceController extends Controller
{
    private const ALLOWED_ROLE = 'superadmin';

    private function denyIfForbidden(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->role_code !== self::ALLOWED_ROLE) {
            return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function dashboard(Request $request, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        return $this->ok('Governance dashboard retrieved successfully.', $service->getDashboard($request->all()));
    }

    public function auditLogs(Request $request, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        return $this->ok('Audit logs retrieved successfully.', $service->getAuditLogs($request->all()));
    }

    public function auditLog(Request $request, int $id, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        $log = $service->getAuditLog($id);

        if (! $log) {
            return response()->json(['success' => false, 'message' => 'Not found.', 'data' => null], Response::HTTP_NOT_FOUND);
        }

        return $this->ok('Audit log retrieved successfully.', ['auditLog' => $log]);
    }

    public function securityEvents(Request $request, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        return $this->ok('Security events retrieved successfully.', $service->getSecurityEvents($request->all()));
    }

    public function securityEvent(Request $request, int $id, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        $event = $service->getSecurityEvent($id);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Not found.', 'data' => null], Response::HTTP_NOT_FOUND);
        }

        return $this->ok('Security event retrieved successfully.', ['securityEvent' => $event]);
    }

    public function resolveSecurityEvent(Request $request, int $id, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        $event = $service->resolveSecurityEvent($id, [
            'resolved_by' => $request->user()?->id,
        ]);

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Not found.', 'data' => null], Response::HTTP_NOT_FOUND);
        }

        return $this->ok('Security event resolved successfully.', ['securityEvent' => $event]);
    }

    public function configurationHistory(Request $request, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        return $this->ok('Configuration history retrieved successfully.', $service->getConfigurationHistory($request->all()));
    }

    public function riskDashboard(Request $request, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        return $this->ok('Risk dashboard retrieved successfully.', ['riskDashboard' => $service->getRiskDashboard($request->all())]);
    }

    public function atRiskStudents(Request $request, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        return $this->ok('At-risk students retrieved successfully.', ['items' => $service->getAtRiskStudents($request->all())]);
    }

    public function investigations(Request $request, EnterpriseGovernanceService $service): JsonResponse
    {
        if ($response = $this->denyIfForbidden($request)) {
            return $response;
        }

        return $this->ok('Investigations retrieved successfully.', $service->getInvestigations($request->all()));
    }

    private function ok(string $message, array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
