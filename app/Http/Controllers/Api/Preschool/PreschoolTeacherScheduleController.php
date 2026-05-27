<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolClassResource;
use App\Http\Resources\Preschool\PreschoolScheduleEntryResource;
use App\Http\Resources\UserResource;
use App\Models\PreschoolClass;
use App\Models\User;
use App\Support\PreschoolTeacherScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolTeacherScheduleController extends Controller
{
    /**
     * Teacher-facing timetable reads stay separate from admin writes so the
     * UI can render read-only views without exposing management actions.
     */
    public function classSchedule(Request $request, PreschoolClass $class, PreschoolTeacherScheduleService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $class->load(['teacher']);
        $items = $service->classSchedule($request->user(), $class);

        return response()->json([
            'success' => true,
            'message' => 'Preschool class schedule retrieved successfully.',
            'data' => [
                'class' => PreschoolClassResource::make($class)->resolve($request),
                'items' => PreschoolScheduleEntryResource::collection($items)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function teacherSchedule(Request $request, User $teacher, PreschoolTeacherScheduleService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $teacher->load(['department', 'role']);
        $items = $service->teacherSchedule($request->user(), $teacher);

        return response()->json([
            'success' => true,
            'message' => 'Preschool teacher schedule retrieved successfully.',
            'data' => [
                'teacher' => UserResource::make($teacher)->resolve($request),
                'items' => PreschoolScheduleEntryResource::collection($items)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function meSchedule(Request $request, PreschoolTeacherScheduleService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        $user = $request->user();
        $items = $service->mySchedule($user);

        return response()->json([
            'success' => true,
            'message' => 'Preschool schedule retrieved successfully.',
            'data' => [
                'teacher' => UserResource::make($user)->resolve($request),
                'items' => PreschoolScheduleEntryResource::collection($items)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
