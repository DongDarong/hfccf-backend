<?php

namespace App\Support;

/**
 * Canonical status constants for monthly assessment submissions.
 *
 * Lifecycle: DRAFT → SUBMITTED → RETURNED → SUBMITTED → FINALIZED → ARCHIVED
 *
 * DRAFT:
 *   - Initial state after creation
 *   - Teachers can add/edit student scores
 *   - Teachers can submit to admin for review
 *   - Teachers can delete (archive)
 *
 * SUBMITTED:
 *   - Locked from teacher edits
 *   - Awaiting admin review
 *   - Admins can return for revision or finalize
 *
 * RETURNED:
 *   - Admin rejected; teacher must revise
 *   - Teachers can edit scores again
 *   - Teachers can re-submit to admin
 *   - Distinct from DRAFT (audit trail shows it was reviewed once)
 *
 * FINALIZED:
 *   - Admin approved; submission is official
 *   - Fully locked; no further edits except admin override
 *   - Data appears in official Assessment Reports
 *   - Grading scale snapshot captured
 *   - Can transition to ARCHIVED
 *
 * ARCHIVED:
 *   - Workflow status (not soft-deleted)
 *   - Remains queryable for history and audit
 *   - Cannot be edited or transitioned
 *   - Grading snapshot and metadata preserved
 */
final class PreschoolMonthlySubmissionStatus
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const RETURNED = 'returned';
    public const FINALIZED = 'finalized';
    public const ARCHIVED = 'archived';

    /**
     * All valid status values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::RETURNED,
            self::FINALIZED,
            self::ARCHIVED,
        ];
    }

    /**
     * Statuses that allow teacher edits.
     *
     * @return array<int, string>
     */
    public static function teacherEditableStatuses(): array
    {
        return [
            self::DRAFT,
            self::RETURNED,
        ];
    }

    /**
     * Statuses that allow teacher submission.
     *
     * @return array<int, string>
     */
    public static function teacherSubmittableStatuses(): array
    {
        return [
            self::DRAFT,
            self::RETURNED,
        ];
    }

    /**
     * Statuses visible in official reports.
     *
     * @return array<int, string>
     */
    public static function officialStatuses(): array
    {
        return [
            self::FINALIZED,
        ];
    }
}
