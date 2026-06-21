<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolSchoolCalendarEventRequest;
use App\Http\Requests\Preschool\UpdatePreschoolSchoolCalendarEventRequest;
use App\Http\Resources\Preschool\PreschoolSchoolCalendarEventResource;
use App\Models\PreschoolSchoolCalendarEvent;
use App\Support\PreschoolAttendanceConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolSchoolCalendarEventController extends Controller
{
    public function index(Request $request, PreschoolAttendanceConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,archived'],
            'type' => ['sometimes', 'nullable', 'string'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
        ]);

        $paginator = $service->listCalendarEvents($filters);

        return response()->json([
            'success' => true,
            'message' => 'Preschool school calendar events retrieved successfully.',
            'data' => [
                'items' => PreschoolSchoolCalendarEventResource::collection($paginator->getCollection())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolSchoolCalendarEventRequest $request, PreschoolAttendanceConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $event = $service->createCalendarEvent($request->validated(), $request->user());
        $event->load('academicYear');

        return response()->json([
            'success' => true,
            'message' => 'Preschool school calendar event created successfully.',
            'data' => [
                'event' => PreschoolSchoolCalendarEventResource::make($event)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, PreschoolSchoolCalendarEvent $event): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $event->load('academicYear');

        return response()->json([
            'success' => true,
            'message' => 'Preschool school calendar event retrieved successfully.',
            'data' => [
                'event' => PreschoolSchoolCalendarEventResource::make($event)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolSchoolCalendarEventRequest $request, PreschoolSchoolCalendarEvent $event, PreschoolAttendanceConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $event = $service->updateCalendarEvent($event, $request->validated(), $request->user());
        $event->load('academicYear');

        return response()->json([
            'success' => true,
            'message' => 'Preschool school calendar event updated successfully.',
            'data' => [
                'event' => PreschoolSchoolCalendarEventResource::make($event)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, PreschoolSchoolCalendarEvent $event, PreschoolAttendanceConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $event = $service->archiveCalendarEvent($event, $request->user());
        $event->load('academicYear');

        return response()->json([
            'success' => true,
            'message' => 'Preschool school calendar event archived successfully.',
            'data' => [
                'event' => PreschoolSchoolCalendarEventResource::make($event)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeSettingsAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
