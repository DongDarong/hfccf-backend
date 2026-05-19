<?php

namespace App\Http\Resources\Notification;

use App\Models\Notification as NotificationModel;
use App\Models\NotificationRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationModel|NotificationRecipient */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $notification = $resource instanceof NotificationRecipient
            ? $resource->notification
            : $resource;

        $recipient = $resource instanceof NotificationRecipient ? $resource : null;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'module' => $notification->module,
            'actionUrl' => $notification->action_url,
            'metadata' => $notification->metadata,
            'readAt' => $recipient?->read_at?->toISOString(),
            'dismissedAt' => $recipient?->dismissed_at?->toISOString(),
            'createdAt' => $notification->created_at?->toISOString(),
        ];
    }
}
