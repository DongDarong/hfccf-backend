<?php

namespace App\Support;

use App\Models\SportMatchEvent;
use App\Models\SportMatchSquadPlayer;
use Illuminate\Support\Collection;

class SportSubstitutionService
{
    /**
     * @param  Collection<int, SportMatchEvent>  $events
     */
    public function assertSubstitutionPair(
        SportMatchSquadPlayer $subject,
        ?SportMatchSquadPlayer $related,
        Collection $events,
        string $eventType,
    ): void {
        if (! $related) {
            throw new \RuntimeException('A substitution requires both leaving and entering players.');
        }

        if ((string) $subject->id === (string) $related->id) {
            throw new \RuntimeException('A player cannot substitute themselves.');
        }

        $subjectHasSubIn = $events->contains(function (SportMatchEvent $event) use ($subject): bool {
            return (string) $event->squad_player_id === (string) $subject->id
                && $event->event_type === SportMatchEventType::SUBSTITUTION_IN;
        });

        $subjectHasSubOut = $events->contains(function (SportMatchEvent $event) use ($subject): bool {
            return (string) $event->squad_player_id === (string) $subject->id
                && $event->event_type === SportMatchEventType::SUBSTITUTION_OUT;
        });

        if ($eventType === SportMatchEventType::SUBSTITUTION_IN && $subjectHasSubIn) {
            throw new \RuntimeException('Player cannot be substituted in twice.');
        }

        if ($eventType === SportMatchEventType::SUBSTITUTION_OUT && $subjectHasSubOut) {
            throw new \RuntimeException('Player cannot be substituted out twice.');
        }

        $relatedHasSubIn = $events->contains(function (SportMatchEvent $event) use ($related): bool {
            return (string) $event->squad_player_id === (string) $related->id
                && $event->event_type === SportMatchEventType::SUBSTITUTION_IN;
        });

        if ($eventType === SportMatchEventType::SUBSTITUTION_OUT && $relatedHasSubIn) {
            throw new \RuntimeException('Replacement player is already participating in the match.');
        }
    }
}
