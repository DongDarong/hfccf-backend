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
    ) {}

    public function index(ListNotificationRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $perPage = min(max((int) ($validated['per_page'] ?? 10), 1), 100);
        $page = max((int) ($validated['page'] ?? 1), 1);
        $status = strtolower(trim((string) ($validated['status'] ?? 'all')));
        $type = strtolower(trim((string) ($validated['type'] ?? '')));
        $module = strtolower(trim((string) ($validated['module'] ?? '')));
        $search = trim((string) ($validated['search'] ?? ''));

        $query = NotificationRecipient::query()
            ->with(['notification.creator', 'notification.targets'])
            ->where('user_id', $user->id)
            ->whereHas('notification');

        if ($status === 'unread') {
            $query->whereNull('read_at')->whereNull('dismissed_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at')->whereNull('dismissed_at');
        } elseif ($status === 'dismissed') {
            $query->whereNotNull('dismissed_at');
        }

        if ($type !== '') {
            $query->whereHas('notification', static function ($builder) use ($type): void {
                $builder->where('type', $type);
            });
        }

        if ($module !== '') {
            $query->whereHas('notification', static function ($builder) use ($module): void {
                $builder->where('module', $module);
            });
        }

        if ($search !== '') {
            $query->whereHas('notification', static function ($builder) use ($search): void {
                $builder->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('title', 'like', '%'.$search.'%')
                        ->orWhere('message', 'like', '%'.$search.'%')
                        ->orWhere('action_url', 'like', '%'.$search.'%');
                });
            });
        }

        $notifications = $query
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

    public function undismiss(Request $request, int $id): JsonResponse
    {
        $recipient = $this->recipientForUser($request, $id);

        if (! $recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        if ($recipient->dismissed_at !== null) {
            $recipient->forceFill(['dismissed_at' => null])->save();
        }

        $recipient->load('notification');

        return response()->json([
            'success' => true,
            'message' => 'Notification restored successfully.',
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
