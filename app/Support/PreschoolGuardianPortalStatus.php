<?php

namespace App\Support;

final class PreschoolGuardianPortalStatus
{
    public const INVITED = 'invited';

    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const REVOKED = 'revoked';

    /**
     * Keep portal states explicit so invitation, activation, and revocation
     * stay in sync across backend services and frontend portal screens.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::INVITED, self::ACTIVE, self::SUSPENDED, self::REVOKED];
    }
}
