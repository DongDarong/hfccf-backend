<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for monthly submission workflow violations.
 *
 * Use factory methods to create specific error conditions clearly.
 */
class PreschoolMonthlySubmissionException extends Exception
{
    protected $errorCode;

    public function __construct(
        string $message = '',
        string $errorCode = 'UNKNOWN_ERROR',
        int $code = 400,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    // ============================================================================
    // Factory Methods for Specific Errors
    // ============================================================================

    public static function submissionNotFound(string $message = ''): self
    {
        return new self(
            $message ?: 'Submission not found.',
            'SUBMISSION_NOT_FOUND',
            404
        );
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(
            $message ?: 'User is not authorized for this action.',
            'UNAUTHORIZED',
            403
        );
    }

    public static function invalidStatusTransition(string $message = ''): self
    {
        return new self(
            $message ?: 'Invalid status transition.',
            'INVALID_STATUS_TRANSITION',
            409
        );
    }

    public static function duplicateSubmission(string $message = ''): self
    {
        return new self(
            $message ?: 'A submission for this period already exists.',
            'DUPLICATE_SUBMISSION',
            409
        );
    }

    public static function invalidStudentClass(string $message = ''): self
    {
        return new self(
            $message ?: 'Student is not enrolled in this class.',
            'INVALID_STUDENT_CLASS',
            422
        );
    }

    public static function invalidScore(string $message = ''): self
    {
        return new self(
            $message ?: 'Score is invalid.',
            'INVALID_SCORE',
            422
        );
    }

    public static function gradeScaleNotConfigured(string $message = ''): self
    {
        return new self(
            $message ?: 'Grade scale has not been configured. Please contact Preschool Admin.',
            'GRADE_SCALE_NOT_CONFIGURED',
            422
        );
    }

    public static function emptySubmission(string $message = ''): self
    {
        return new self(
            $message ?: 'Submission contains no assessments.',
            'EMPTY_SUBMISSION',
            422
        );
    }

    public static function immutableSubmission(string $message = ''): self
    {
        return new self(
            $message ?: 'This submission is locked and cannot be edited.',
            'IMMUTABLE_SUBMISSION',
            409
        );
    }

    public static function invalidAcademicYear(string $message = ''): self
    {
        return new self(
            $message ?: 'Academic year is invalid or inactive.',
            'INVALID_ACADEMIC_YEAR',
            422
        );
    }

    public static function invalidCategory(string $message = ''): self
    {
        return new self(
            $message ?: 'Assessment category is invalid or inactive.',
            'INVALID_CATEGORY',
            422
        );
    }

    public static function invalidInput(string $message = ''): self
    {
        return new self(
            $message ?: 'Invalid input provided.',
            'INVALID_INPUT',
            422
        );
    }
}
