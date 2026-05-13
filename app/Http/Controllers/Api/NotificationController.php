<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationRequest;
use App\Http\Requests\Notification\StoreNotificationRequest;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\NotificationRecipient;
use App\Services\NotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(ListNotificationRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $perPage = min(max((int) ($validated['per_page'] ?? 10), 1), 100);
        $page = max((int) ($validated['page'] ?? 1), 1);

        $notifications = NotificationRecipient::query()
            ->with(['notification.creator', 'notification.targets'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => [
                'items' => NotificationResource::collection($notifications->getCollection())->resolve($request),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'from' => $notifications->firstItem(),
                    'to' => $notifications->lastItem(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = NotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Unread notification count retrieved successfully.',
            'data' => [
                'count' => $count,
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        try {
            $notification = $this->notificationService->createNotification(
                $request->user(),
                $request->validated(),
            );
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $notification->load(['creator', 'targets']);

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully.',
            'data' => [
                'notification' => NotificationResource::make($notification)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $recipient = $this->recipientForUser($request, $id);

        if (! $recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        if ($recipient->read_at === null) {
            $recipient->forceFill(['read_at' => now()])->save();
        }

        $recipient->load('notification');

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => [
                'notification' => NotificationResource::make($recipient)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updatedCount = NotificationRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data' => [
                'updatedCount' => $updatedCount,
            ],
        ], Response::HTTP_OK);
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $recipient = $this->recipientForUser($request, $id);

        if (! $recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        $recipient->forceFill([
            'read_at' => $recipient->read_at ?? now(),
            'dismissed_at' => now(),
        ])->save();

        $recipient->load('notification');

        return response()->json([
            'success' => true,
            'message' => 'Notification dismissed successfully.',
            'data' => [
                'notification' => NotificationResource::make($recipient)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function recipientForUser(Request $request, int $notificationId): ?NotificationRecipient
    {
        return NotificationRecipient::query()
            ->with('notification')
            ->where('notification_id', $notificationId)
            ->where('user_id', $request->user()->id)
            ->first();
    }
}
