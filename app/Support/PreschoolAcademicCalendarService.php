<?php

namespace App\Support;

/**
 * Calendar service alias kept for the phase-2 settings contract.
 *
 * The existing lifecycle service already owns the year/term persistence logic,
 * so this class acts as a stable semantic entry point for the new settings
 * architecture without duplicating behavior.
 */
class PreschoolAcademicCalendarService extends PreschoolAcademicLifecycleService
{
}
