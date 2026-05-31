<?php

namespace App\Events;

use App\Models\PreschoolPayment;
use App\Models\PreschoolStudent;

/**
 * PaymentCreatedOnEnrollment
 *
 * Fired after a pending tuition payment record is auto-created when an
 * enrollment application transitions to "enrolled" status via
 * PreschoolEnrollmentService::enrollAsStudent(). Both the payment and the
 * student are guaranteed to be fully persisted in the database at the time
 * this event is dispatched — the transaction has already committed.
 *
 * Listeners may use this event to:
 *  - Send a payment-due notification to the guardian
 *  - Push the payment summary to an external billing system
 *  - Log a financial event in an audit trail
 *
 * @property PreschoolPayment $payment The newly created pending payment record
 * @property PreschoolStudent $student The newly enrolled student
 */
class PaymentCreatedOnEnrollment
{
    /**
     * @param  PreschoolPayment  $payment  The pending payment auto-created on enrollment
     * @param  PreschoolStudent  $student  The newly enrolled student who owes the payment
     */
    public function __construct(
        public readonly PreschoolPayment $payment,
        public readonly PreschoolStudent $student,
    ) {}
}
