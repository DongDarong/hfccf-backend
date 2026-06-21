<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolGuardianCommunicationResource;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\PreschoolGuardianCommunicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolGuardianCommunicationController extends Controller
{
    public function index(Request $request, PreschoolGuardianCommunicationService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $paginator = $service->listCommunications($request->user(), $request->query());

        return response()->json([
            'success' => true,
            'message' => 'Guardian communications retrieved successfully.',
            'data' => [
                'items' => PreschoolGuardianCommunicationResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => $this->paginationShape($paginator),
            ],
        ], Response::HTTP_OK);
    }

    public function studentTimeline(Request $request, PreschoolStudent $student, PreschoolGuardianCommunicationService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user(), $student)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Student guardian communications retrieved successfully.',
            'data' => $service->studentTimeline($student, $request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function guardianTimeline(Request $request, PreschoolGuardian $guardian, PreschoolGuardianCommunicationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Guardian communications retrieved successfully.',
            'data' => $service->guardianTimeline($guardian, $request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function store(Request $request, PreschoolStudent $student, PreschoolGuardianCommunicationService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user(), $student)) {
            return $response;
        }

        $data = $request->validate([
            'guardian_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_guardians,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'severity' => ['sometimes', 'nullable', 'in:low,medium,high,critical'],
            'channel' => ['sometimes', 'nullable', 'in:in_app,phone,sms,email,manual_note'],
        ]);

        $guardian = isset($data['guardian_id']) && $data['guardian_id'] !== null
            ? PreschoolGuardian::query()->find($data['guardian_id'])
            : null;

        $communication = $service->createManualNote($student, $guardian, $request->user(), $data);

        return response()->json([
            'success' => true,
            'message' => 'Guardian communication created successfully.',
            'data' => [
                'communication' => PreschoolGuardianCommunicationResource::make($communication)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function markSent(Request $request, PreschoolGuardianCommunication $communication, PreschoolGuardianCommunicationService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user(), $communication->student()->firstOrFail())) {
            return $response;
        }

        $updated = $service->markSent($communication, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Guardian communication marked as sent successfully.',
            'data' => [
                'communication' => PreschoolGuardianCommunicationResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function acknowledge(Request $request, PreschoolGuardianCommunication $communication, PreschoolGuardianCommunicationService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user(), $communication->student()->firstOrFail())) {
            return $response;
        }

        $updated = $service->acknowledge($communication, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Guardian communication acknowledged successfully.',
            'data' => [
                'communication' => PreschoolGuardianCommunicationResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function cancel(Request $request, PreschoolGuardianCommunication $communication, PreschoolGuardianCommunicationService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user(), $communication->student()->firstOrFail())) {
            return $response;
        }

        $updated = $service->cancel($communication, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Guardian communication cancelled successfully.',
            'data' => [
                'communication' => PreschoolGuardianCommunicationResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user, ?PreschoolStudent $student = null): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        if ($user->role_code === 'teacher-preschool' && $student && $this->teacherCanAccessStudent($user, $student)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }

    private function authorizeWriter(?User $user, PreschoolStudent $student): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        if ($user->role_code === 'teacher-preschool' && $this->teacherCanAccessStudent($user, $student)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }

    private function teacherCanAccessStudent(User $user, PreschoolStudent $student): bool
    {
        return $student->classes()
            ->where('teacher_user_id', $user->id)
            ->exists();
    }

    private function paginationShape($paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
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
