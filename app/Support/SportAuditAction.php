<?php

namespace App\Support;

class SportAuditAction
{
    public const PLAYER_REQUEST_CREATED = 'player_request_created';

    public const PLAYER_APPROVED = 'player_approved';

    public const PLAYER_REJECTED = 'player_rejected';

    public const MATCH_REQUEST_CREATED = 'match_request_created';

    public const MATCH_APPROVED = 'match_approved';

    public const MATCH_REJECTED = 'match_rejected';

    public const COACH_ASSIGNMENT_CREATED = 'coach_assignment_created';

    public const COACH_ASSIGNMENT_UPDATED = 'coach_assignment_updated';

    public const COACH_ASSIGNMENT_DEACTIVATED = 'coach_assignment_deactivated';

    public const ROSTER_MEMBERSHIP_ADDED = 'roster_membership_added';

    public const ROSTER_MEMBERSHIP_UPDATED = 'roster_membership_updated';

    public const ROSTER_MEMBERSHIP_REMOVED = 'roster_membership_removed';

    public const PLAYER_LIFECYCLE_CHANGED = 'player_lifecycle_changed';

    public const MATCH_SQUAD_SUBMITTED = 'match_squad_submitted';

    public const MATCH_SQUAD_APPROVED = 'match_squad_approved';

    public const MATCH_SQUAD_LOCKED = 'match_squad_locked';

    public const EQUIPMENT_ITEM_CREATED = 'equipment_item_created';

    public const EQUIPMENT_ITEM_UPDATED = 'equipment_item_updated';

    public const EQUIPMENT_REQUEST_CREATED = 'equipment_request_created';

    public const EQUIPMENT_REQUEST_APPROVED = 'equipment_request_approved';

    public const EQUIPMENT_REQUEST_REJECTED = 'equipment_request_rejected';

    public const EQUIPMENT_REQUEST_ISSUED = 'equipment_request_issued';

    public const EQUIPMENT_REQUEST_RETURNED = 'equipment_request_returned';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::PLAYER_REQUEST_CREATED,
            self::PLAYER_APPROVED,
            self::PLAYER_REJECTED,
            self::MATCH_REQUEST_CREATED,
            self::MATCH_APPROVED,
            self::MATCH_REJECTED,
            self::COACH_ASSIGNMENT_CREATED,
            self::COACH_ASSIGNMENT_UPDATED,
            self::COACH_ASSIGNMENT_DEACTIVATED,
            self::ROSTER_MEMBERSHIP_ADDED,
            self::ROSTER_MEMBERSHIP_UPDATED,
            self::ROSTER_MEMBERSHIP_REMOVED,
            self::PLAYER_LIFECYCLE_CHANGED,
            self::MATCH_SQUAD_SUBMITTED,
            self::MATCH_SQUAD_APPROVED,
            self::MATCH_SQUAD_LOCKED,
            self::EQUIPMENT_ITEM_CREATED,
            self::EQUIPMENT_ITEM_UPDATED,
            self::EQUIPMENT_REQUEST_CREATED,
            self::EQUIPMENT_REQUEST_APPROVED,
            self::EQUIPMENT_REQUEST_REJECTED,
            self::EQUIPMENT_REQUEST_ISSUED,
            self::EQUIPMENT_REQUEST_RETURNED,
        ];
    }
}
