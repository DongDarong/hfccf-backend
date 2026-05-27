<?php

namespace App\Support;

use App\Models\SportMatchEvent;
use Illuminate\Support\Collection;

class SportMatchTimelineService
{
    /**
     * Keep the event order stable even when timestamps tie. Historical match data must not reshuffle
     * if a late edit reuses the same minute.
     *
     * @param  Collection<int, SportMatchEvent>  $events
     * @return Collection<int, SportMatchEvent>
     */
    public function sortEvents(Collection $events): Collection
    {
        return $events
            ->sortBy([
                ['minute', 'asc'],
                ['extra_time_minute', 'asc'],
                ['stoppage_minute', 'asc'],
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
