<?php

namespace App\Support;

use App\Models\SportPlayer;
use App\Models\User;
use Illuminate\Support\Carbon;

class SportPlayerStatusTransitionService
{
    public function canTransition(SportPlayer $player, string $nextStatus): bool
    {
        $nextStatus = strtolower(trim($nextStatus));
        $currentStatus = strtolower(trim((string) ($player->roster_status ?: $player->status)));

        if ($nextStatus === $currentStatus) {
            return true;
        }

        if ($currentStatus === SportPlayerRosterStatus::ARCHIVED) {
            return false;
        }

        return in_array($nextStatus, SportPlayerRosterStatus::values(), true);
    }

    public function transition(SportPlayer $player, string $nextStatus, ?User $changedBy = null, array $context = []): SportPlayer
    {
        $nextStatus = strtolower(trim($nextStatus));

        if (! $this->canTransition($player, $nextStatus)) {
            throw new \RuntimeException('Invalid player lifecycle transition.');
        }

        if ($player->approval_status !== SportPlayerApprovalStatus::APPROVED && ! in_array($nextStatus, [SportPlayerRosterStatus::INACTIVE, SportPlayerRosterStatus::ARCHIVED], true)) {
            throw new \RuntimeException('Player must be approved before joining an active roster.');
        }

        if (in_array($nextStatus, [SportPlayerRosterStatus::ACTIVE, SportPlayerRosterStatus::INJURED, SportPlayerRosterStatus::SUSPENDED], true) && ! $player->team_id) {
            throw new \RuntimeException('Player must belong to a team before entering roster activity.');
        }

        $player->forceFill([
            'roster_status' => $nextStatus,
            'status' => $nextStatus,
            'status_notes' => $context['notes'] ?? $player->status_notes,
            'injury_status' => $nextStatus === SportPlayerRosterStatus::INJURED
                ? ($context['injury_status'] ?? 'injured')
                : ($nextStatus === SportPlayerRosterStatus::ACTIVE ? null : ($context['injury_status'] ?? $player->injury_status)),
            'disciplinary_status' => $nextStatus === SportPlayerRosterStatus::SUSPENDED
                ? ($context['disciplinary_status'] ?? 'suspended')
                : ($nextStatus === SportPlayerRosterStatus::ACTIVE ? null : ($context['disciplinary_status'] ?? $player->disciplinary_status)),
            'archived_at' => $nextStatus === SportPlayerRosterStatus::ARCHIVED ? ($player->archived_at ?? Carbon::now()) : null,
        ])->save();

        if (array_key_exists('team_id', $context)) {
            $player->forceFill([
                'team_id' => $context['team_id'],
            ])->save();
        }

        return $player->refresh();
    }
}
