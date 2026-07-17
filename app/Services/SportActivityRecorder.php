<?php

namespace App\Services;

use App\Models\CoachTeamAssignment;
use App\Models\SportEquipmentItem;
use App\Models\SportEquipmentRequest;
use App\Models\SportMatch;
use App\Models\SportMatchSquad;
use App\Models\SportPlayer;
use App\Models\SportPlayerTeamMembership;
use App\Models\SportTeam;
use App\Models\User;
use App\Support\SportEquipmentRequestStatus;
use App\Support\SportAuditAction;

class SportActivityRecorder
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SportNotificationDispatcher $notificationDispatcher,
    ) {}

    public function playerRequestCreated(SportPlayer $player, User $actor): void
    {
        $this->audit('sport', SportAuditAction::PLAYER_REQUEST_CREATED, 'sport_player', $player->id, $this->playerLabel($player), null, $this->playerPayload($player), $this->context($actor));

        $payload = $this->notificationPayload(
            'info',
            'New sport player request pending approval.',
            $this->playerLabel($player).' requires approval.',
            $actor,
            ['entityType' => 'sport_player', 'entityId' => $player->id, 'teamId' => $player->team_id]
        );

        $this->notificationDispatcher->notifyRole('adminsport', $payload);
        $this->notificationDispatcher->notifyRole('superadmin', $payload);
    }

    public function playerDecision(SportPlayer $player, User $actor, bool $approved, ?string $reason = null): void
    {
        $action = $approved ? SportAuditAction::PLAYER_APPROVED : SportAuditAction::PLAYER_REJECTED;

        $this->audit('sport', $action, 'sport_player', $player->id, $this->playerLabel($player), null, $this->playerPayload($player), $this->context($actor, ['reason' => $reason]));

        if ($creator = $player->createdBy) {
            $this->notificationDispatcher->notifyUser($creator, $this->notificationPayload(
                $approved ? 'success' : 'warning',
                $approved ? 'Sport player request approved.' : 'Sport player request rejected.',
                $this->playerLabel($player).' has been '.($approved ? 'approved' : 'rejected').'.',
                $actor,
                ['entityType' => 'sport_player', 'entityId' => $player->id, 'reason' => $reason]
            ));
        }
    }

    public function matchRequestCreated(SportMatch $match, User $actor): void
    {
        $this->audit('sport', SportAuditAction::MATCH_REQUEST_CREATED, 'sport_match', $match->id, $this->matchLabel($match), null, $this->matchPayload($match), $this->context($actor));

        $payload = $this->notificationPayload(
            'info',
            'New sport match request pending approval.',
            $this->matchLabel($match).' requires approval.',
            $actor,
            ['entityType' => 'sport_match', 'entityId' => $match->id, 'teamId' => $match->home_team_id]
        );

        $this->notificationDispatcher->notifyRole('adminsport', $payload);
        $this->notificationDispatcher->notifyRole('superadmin', $payload);
    }

    public function matchDecision(SportMatch $match, User $actor, bool $approved, ?string $reason = null): void
    {
        $action = $approved ? SportAuditAction::MATCH_APPROVED : SportAuditAction::MATCH_REJECTED;

        $this->audit('sport', $action, 'sport_match', $match->id, $this->matchLabel($match), null, $this->matchPayload($match), $this->context($actor, ['reason' => $reason]));

        if ($creator = $match->creator) {
            $this->notificationDispatcher->notifyUser($creator, $this->notificationPayload(
                $approved ? 'success' : 'warning',
                $approved ? 'Sport match request approved.' : 'Sport match request rejected.',
                $this->matchLabel($match).' has been '.($approved ? 'approved' : 'rejected').'.',
                $actor,
                ['entityType' => 'sport_match', 'entityId' => $match->id, 'reason' => $reason]
            ));
        }
    }

    public function coachAssignmentChanged(CoachTeamAssignment $assignment, User $actor, string $action): void
    {
        $team = $assignment->team;
        $coach = $assignment->coach;

        $this->audit('sport', $action, 'coach_team_assignment', $assignment->id, $this->assignmentLabel($assignment), null, $this->assignmentPayload($assignment), $this->context($actor));

        if ($coach) {
            $this->notificationDispatcher->notifyUser($coach, $this->notificationPayload(
                'info',
                'Sport coach assignment updated.',
                'Your team assignment status has been updated.',
                $actor,
                ['entityType' => 'coach_team_assignment', 'entityId' => $assignment->id, 'teamId' => $team?->id]
            ));
        }
    }

    public function rosterMembershipChanged(SportPlayerTeamMembership $membership, User $actor, string $action): void
    {
        $team = $membership->team;
        $player = $membership->player;

        $this->audit('sport', $action, 'sport_player_team_membership', $membership->id, $this->membershipLabel($membership), null, $this->membershipPayload($membership), $this->context($actor));

        if ($coach = $this->teamCoach($team)) {
            $this->notificationDispatcher->notifyUser($coach, $this->notificationPayload(
                'info',
                'Sport roster updated.',
                'Roster changes were made for '.$this->teamLabel($team).'.',
                $actor,
                ['entityType' => 'sport_player_team_membership', 'entityId' => $membership->id, 'playerId' => $player?->id]
            ));
        }
    }

    public function playerLifecycleChanged(SportPlayer $player, User $actor, string $action, array $oldValues = [], array $newValues = []): void
    {
        $this->audit('sport', $action, 'sport_player', $player->id, $this->playerLabel($player), $oldValues, $newValues, $this->context($actor));

        $teamReference = $player->team ?? ($oldValues['team_id'] ?? null);

        if ($coach = $this->teamCoach($teamReference)) {
            $this->notificationDispatcher->notifyUser($coach, $this->notificationPayload(
                'info',
                'Sport player lifecycle updated.',
                $this->playerLabel($player).' status has changed.',
                $actor,
                ['entityType' => 'sport_player', 'entityId' => $player->id, 'teamId' => $player->team_id]
            ));
        }
    }

    public function squadChanged(SportMatchSquad $squad, User $actor, string $action): void
    {
        $match = $squad->match;
        $team = $squad->team;

        $this->audit('sport', $action, 'sport_match_squad', $squad->id, $this->squadLabel($squad), null, $this->squadPayload($squad), $this->context($actor));

        $payload = $this->notificationPayload(
            $action === SportAuditAction::MATCH_SQUAD_SUBMITTED ? 'info' : 'success',
            'Sport match squad updated.',
            $this->squadLabel($squad).' has been '.str_replace('_', ' ', $action).'.',
            $actor,
            ['entityType' => 'sport_match_squad', 'entityId' => $squad->id, 'matchId' => $match?->id, 'teamId' => $team?->id]
        );

        if ($action === SportAuditAction::MATCH_SQUAD_SUBMITTED) {
            $this->notificationDispatcher->notifyRole('adminsport', $payload);
            $this->notificationDispatcher->notifyRole('superadmin', $payload);
        } elseif ($coach = $this->teamCoach($team)) {
            $this->notificationDispatcher->notifyUser($coach, $payload);
        }
    }

    public function equipmentItemCreated(SportEquipmentItem $item, User $actor): void
    {
        $this->audit('sport', SportAuditAction::EQUIPMENT_ITEM_CREATED, 'sport_equipment_item', $item->id, $this->equipmentItemLabel($item), null, $this->equipmentItemPayload($item), $this->context($actor));

        $payload = $this->notificationPayload(
            'info',
            'Sport equipment item created.',
            $this->equipmentItemLabel($item).' has been created.',
            $actor,
            ['entityType' => 'sport_equipment_item', 'entityId' => $item->id]
        );

        $this->notificationDispatcher->notifyRole('adminsport', $payload);
        $this->notificationDispatcher->notifyRole('superadmin', $payload);
    }

    public function equipmentItemUpdated(SportEquipmentItem $item, User $actor, array $oldValues = [], array $newValues = []): void
    {
        $this->audit('sport', SportAuditAction::EQUIPMENT_ITEM_UPDATED, 'sport_equipment_item', $item->id, $this->equipmentItemLabel($item), $oldValues, $newValues, $this->context($actor));
    }

    public function equipmentRequestCreated(SportEquipmentRequest $request, User $actor): void
    {
        $this->audit('sport', SportAuditAction::EQUIPMENT_REQUEST_CREATED, 'sport_equipment_request', $request->id, $this->equipmentRequestLabel($request), null, $this->equipmentRequestPayload($request), $this->context($actor));

        $payload = $this->notificationPayload(
            'info',
            'Sport equipment request submitted.',
            $this->equipmentRequestLabel($request).' requires review.',
            $actor,
            ['entityType' => 'sport_equipment_request', 'entityId' => $request->id, 'teamId' => $request->team_id]
        );

        $this->notificationDispatcher->notifyRole('adminsport', $payload);
        $this->notificationDispatcher->notifyRole('superadmin', $payload);
    }

    public function equipmentRequestReviewed(SportEquipmentRequest $request, User $actor, string $action, ?string $reason = null): void
    {
        $auditAction = $action === SportEquipmentRequestStatus::APPROVED
            ? SportAuditAction::EQUIPMENT_REQUEST_APPROVED
            : SportAuditAction::EQUIPMENT_REQUEST_REJECTED;

        $this->audit('sport', $auditAction, 'sport_equipment_request', $request->id, $this->equipmentRequestLabel($request), null, $this->equipmentRequestPayload($request), $this->context($actor, ['reason' => $reason]));

        if ($coach = $request->coach) {
            $this->notificationDispatcher->notifyUser($coach, $this->notificationPayload(
                $action === SportEquipmentRequestStatus::APPROVED ? 'success' : 'warning',
                $action === SportEquipmentRequestStatus::APPROVED ? 'Sport equipment request approved.' : 'Sport equipment request rejected.',
                $this->equipmentRequestLabel($request).' has been '.($action === SportEquipmentRequestStatus::APPROVED ? 'approved' : 'rejected').'.',
                $actor,
                ['entityType' => 'sport_equipment_request', 'entityId' => $request->id, 'reason' => $reason]
            ));
        }
    }

    public function equipmentRequestIssued(SportEquipmentRequest $request, User $actor): void
    {
        $this->audit('sport', SportAuditAction::EQUIPMENT_REQUEST_ISSUED, 'sport_equipment_request', $request->id, $this->equipmentRequestLabel($request), null, $this->equipmentRequestPayload($request), $this->context($actor));

        if ($coach = $request->coach) {
            $this->notificationDispatcher->notifyUser($coach, $this->notificationPayload(
                'success',
                'Sport equipment issued.',
                $this->equipmentRequestLabel($request).' has been issued.',
                $actor,
                ['entityType' => 'sport_equipment_request', 'entityId' => $request->id]
            ));
        }
    }

    public function equipmentRequestReturned(SportEquipmentRequest $request, User $actor): void
    {
        $this->audit('sport', SportAuditAction::EQUIPMENT_REQUEST_RETURNED, 'sport_equipment_request', $request->id, $this->equipmentRequestLabel($request), null, $this->equipmentRequestPayload($request), $this->context($actor));

        if ($coach = $request->coach) {
            $this->notificationDispatcher->notifyUser($coach, $this->notificationPayload(
                'success',
                'Sport equipment returned.',
                $this->equipmentRequestLabel($request).' has been returned.',
                $actor,
                ['entityType' => 'sport_equipment_request', 'entityId' => $request->id]
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $domain, string $action, string $entityType, ?int $entityId, ?string $entityLabel, ?array $oldValues, ?array $newValues, array $metadata = []): void
    {
        $this->auditLogService->recordSafely([
            'actor_user_id' => request()?->user()?->id,
            'domain' => $domain,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Keep audit metadata consistent so downstream consumers can rely on a
     * stable actor payload without leaking request-specific internals.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function context(User $actor, array $metadata = []): array
    {
        return array_merge([
            'actorUserId' => $actor->id,
            'actorName' => trim($actor->first_name.' '.$actor->last_name),
            'actorRole' => $actor->role?->name ?? $actor->role_name ?? null,
        ], $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function notificationPayload(string $type, string $title, string $message, User $actor, array $metadata = []): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'module' => 'sport',
            'created_by' => $actor->id,
            'metadata' => array_merge($metadata, [
                'actorUserId' => $actor->id,
                'actorName' => trim($actor->first_name.' '.$actor->last_name),
            ]),
        ];
    }

    private function teamCoach(SportTeam|int|string|null $team): ?User
    {
        if (! $team) {
            return null;
        }

        if (! $team instanceof SportTeam) {
            $team = SportTeam::query()->with(['activeCoachAssignment.coach', 'coach'])->find($team);
        } else {
            $team->loadMissing(['activeCoachAssignment.coach', 'coach']);
        }

        if (! $team) {
            return null;
        }

        return $team->activeCoachAssignment?->coach ?: $team->coach;
    }

    private function playerLabel(SportPlayer $player): string
    {
        return trim($player->first_name.' '.$player->last_name);
    }

    private function matchLabel(SportMatch $match): string
    {
        return trim(($match->homeTeam?->name ?? 'Home').' vs '.($match->awayTeam?->name ?? 'Away'));
    }

    private function assignmentLabel(CoachTeamAssignment $assignment): string
    {
        return trim(($assignment->coach?->first_name ?? '').' '.($assignment->coach?->last_name ?? '').' → '.($assignment->team?->name ?? 'Team'));
    }

    private function membershipLabel(SportPlayerTeamMembership $membership): string
    {
        return trim(($membership->player?->first_name ?? '').' '.($membership->player?->last_name ?? '').' → '.($membership->team?->name ?? 'Team'));
    }

    private function teamLabel(SportTeam|int|string|null $team): string
    {
        if (! $team) {
            return 'Team';
        }

        if ($team instanceof SportTeam) {
            return $team->name ?? 'Team';
        }

        return SportTeam::query()->find($team)?->name ?? 'Team';
    }

    private function equipmentItemLabel(SportEquipmentItem $item): string
    {
        return trim($item->name.' ('.$item->equipment_code.')');
    }

    /**
     * @return array<string, mixed>
     */
    private function equipmentItemPayload(SportEquipmentItem $item): array
    {
        return [
            'equipmentItemId' => $item->id,
            'equipmentCode' => $item->equipment_code,
            'name' => $item->name,
            'category' => $item->category,
            'unit' => $item->unit,
            'totalQuantity' => (int) $item->total_quantity,
            'availableQuantity' => (int) $item->available_quantity,
            'minimumStockLevel' => (int) $item->minimum_stock_level,
            'storageLocation' => $item->storage_location,
            'status' => $item->status,
        ];
    }

    private function equipmentRequestLabel(SportEquipmentRequest $request): string
    {
        return trim(($request->item?->name ?? 'Equipment').' → '.($request->team?->name ?? 'Team'));
    }

    /**
     * @return array<string, mixed>
     */
    private function equipmentRequestPayload(SportEquipmentRequest $request): array
    {
        return [
            'equipmentRequestId' => $request->id,
            'requestCode' => $request->request_code,
            'equipmentItemId' => $request->equipment_item_id,
            'teamId' => $request->team_id,
            'coachUserId' => $request->coach_user_id,
            'requestedQuantity' => (int) $request->requested_quantity,
            'approvedQuantity' => $request->approved_quantity !== null ? (int) $request->approved_quantity : null,
            'issuedQuantity' => (int) $request->issued_quantity,
            'returnedQuantity' => (int) $request->returned_quantity,
            'damagedQuantity' => (int) $request->damaged_quantity,
            'missingQuantity' => (int) $request->missing_quantity,
            'status' => $request->status,
        ];
    }

    private function squadLabel(SportMatchSquad $squad): string
    {
        return trim(($squad->team?->name ?? 'Team').' squad');
    }

    /**
     * @return array<string, mixed>
     */
    private function playerPayload(SportPlayer $player): array
    {
        return [
            'playerId' => $player->id,
            'playerCode' => $player->player_code,
            'teamId' => $player->team_id,
            'approvalStatus' => $player->approval_status,
            'rosterStatus' => $player->roster_status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function matchPayload(SportMatch $match): array
    {
        return [
            'matchId' => $match->id,
            'matchCode' => $match->match_code,
            'homeTeamId' => $match->home_team_id,
            'awayTeamId' => $match->away_team_id,
            'approvalStatus' => $match->approval_status,
            'status' => $match->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(CoachTeamAssignment $assignment): array
    {
        return [
            'assignmentId' => $assignment->id,
            'coachUserId' => $assignment->coach_user_id,
            'teamId' => $assignment->team_id,
            'status' => $assignment->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipPayload(SportPlayerTeamMembership $membership): array
    {
        return [
            'membershipId' => $membership->id,
            'playerId' => $membership->player_id,
            'teamId' => $membership->team_id,
            'status' => $membership->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function squadPayload(SportMatchSquad $squad): array
    {
        return [
            'squadId' => $squad->id,
            'matchId' => $squad->match_id,
            'teamId' => $squad->team_id,
            'status' => $squad->status,
        ];
    }
}
