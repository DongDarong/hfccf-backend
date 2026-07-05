<?php

namespace App\Services;

use App\Models\PreschoolWorkflowEvent;
use App\Models\PreschoolWorkflowInstance;
use Illuminate\Support\Collection;

class PreschoolWorkflowTimelineService
{
    public function buildTimeline(PreschoolWorkflowInstance $instance): array
    {
        $instance->loadMissing(['events.actor', 'events.fromStep', 'events.toStep', 'definition', 'currentStep', 'approvals']);

        return $instance->events
            ->sortBy('created_at')
            ->values()
            ->map(function (PreschoolWorkflowEvent $event): array {
                return [
                    'id' => $event->id,
                    'eventType' => $event->event_type,
                    'title' => $event->title,
                    'description' => $event->description,
                    'actor' => $event->actor ? [
                        'id' => $event->actor->id,
                        'firstName' => $event->actor->first_name,
                        'lastName' => $event->actor->last_name,
                        'username' => $event->actor->username,
                        'email' => $event->actor->email,
                        'roleCode' => $event->actor->role_code,
                    ] : null,
                    'fromStatus' => $event->from_status,
                    'toStatus' => $event->to_status,
                    'fromStep' => $event->fromStep ? [
                        'id' => $event->fromStep->id,
                        'key' => $event->fromStep->key,
                        'name' => $event->fromStep->name,
                    ] : null,
                    'toStep' => $event->toStep ? [
                        'id' => $event->toStep->id,
                        'key' => $event->toStep->key,
                        'name' => $event->toStep->name,
                    ] : null,
                    'metadata' => $event->metadata ?? [],
                    'createdAt' => $event->created_at?->toISOString(),
                ];
            })
            ->all();
    }

    public function mergeWithSourceEvents(PreschoolWorkflowInstance $instance, array $timeline = []): array
    {
        return $timeline;
    }
}
