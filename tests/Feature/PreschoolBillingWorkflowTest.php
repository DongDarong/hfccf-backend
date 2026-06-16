<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClass;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolPayment;
use App\Models\PreschoolReceipt;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\PreschoolBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class PreschoolBillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_create_draft_invoice(): void
    {
        $context = $this->createBillingContext();

        $response = $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-TEST-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 10,
            'items' => [
                ['description' => 'Tuition fee', 'quantity' => 1, 'unit_price' => 100, 'sort_order' => 1],
                ['description' => 'Lunch fee', 'quantity' => 2, 'unit_price' => 20, 'sort_order' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.invoice.invoiceNumber', 'INV-TEST-001')
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.subtotal', 140)
            ->assertJsonPath('data.invoice.totalAmount', 130);

        $this->assertDatabaseHas('preschool_invoices', [
            'invoice_number' => 'INV-TEST-001',
            'status' => 'draft',
            'subtotal' => 140.0,
            'total_amount' => 130.0,
        ]);

        $this->assertDatabaseCount('preschool_invoice_items', 2);
    }

    public function test_update_draft_invoice(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);

        $response = $this->actingWithToken($context['admin'])->putJson('/api/preschool/invoices/'.$invoice->id, [
            'discount_amount' => 20,
            'items' => [
                ['description' => 'Tuition fee', 'quantity' => 1, 'unit_price' => 120, 'sort_order' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.discountAmount', 20)
            ->assertJsonPath('data.invoice.totalAmount', 100);

        $this->assertDatabaseHas('preschool_invoices', [
            'id' => $invoice->id,
            'discount_amount' => 20.0,
            'total_amount' => 100.0,
        ]);

        $this->assertDatabaseCount('preschool_invoice_items', 1);
    }

    public function test_issue_invoice_and_block_direct_editing(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'issued');

        $this->actingWithToken($context['admin'])->putJson('/api/preschool/invoices/'.$invoice->id, [
            'discount_amount' => 5,
        ])->assertStatus(422);

        $this->assertDatabaseHas('preschool_invoices', [
            'id' => $invoice->id,
            'status' => 'issued',
        ]);
    }

    public function test_cancel_invoice(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'cancelled');

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 25,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertStatus(422);

        $this->assertDatabaseHas('preschool_invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_draft_invoice_can_be_deleted(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);

        $response = $this->actingWithToken($context['admin'])->deleteJson('/api/preschool/invoices/'.$invoice->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice.id', $invoice->id);

        $this->assertSoftDeleted('preschool_invoices', [
            'id' => $invoice->id,
        ]);

        $this->actingWithToken($context['admin'])->getJson('/api/preschool/invoices')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_issued_invoice_cannot_be_deleted(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $this->actingWithToken($context['admin'])->deleteJson('/api/preschool/invoices/'.$invoice->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('preschool_invoices', [
            'id' => $invoice->id,
            'status' => 'issued',
            'deleted_at' => null,
        ]);
    }

    public function test_partial_invoice_cannot_be_deleted(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 40,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated();

        $this->actingWithToken($context['admin'])->deleteJson('/api/preschool/invoices/'.$invoice->id)
            ->assertStatus(422);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertNull($invoice->deleted_at);
    }

    public function test_paid_invoice_cannot_be_deleted(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 120,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated();

        $this->actingWithToken($context['admin'])->deleteJson('/api/preschool/invoices/'.$invoice->id)
            ->assertStatus(422);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNull($invoice->deleted_at);
    }

    public function test_cancelled_invoice_cannot_be_deleted(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/cancel')->assertOk();

        $this->actingWithToken($context['admin'])->deleteJson('/api/preschool/invoices/'.$invoice->id)
            ->assertStatus(422);

        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status);
        $this->assertNull($invoice->deleted_at);
    }

    public function test_payment_reduces_invoice_balance_and_updates_status(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $payment = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 40,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $payment->assertCreated()
            ->assertJsonPath('data.payment.invoiceId', $invoice->id)
            ->assertJsonPath('data.payment.paymentStatus', 'paid');

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('40.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('80.00', number_format((float) $invoice->balance_due, 2, '.', ''));

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', number_format((float) $invoice->balance_due, 2, '.', ''));
    }

    public function test_receipt_generation_prevents_duplicates_unless_reissued(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $payment = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 120,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->json('data.payment');

        $first = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments/'.$payment['id'].'/receipt');
        $first->assertCreated()->assertJsonPath('data.receipt.paymentId', $payment['id']);

        $receiptId = $first->json('data.receipt.id');
        $receiptNumber = $first->json('data.receipt.receiptNumber');

        $second = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments/'.$payment['id'].'/receipt');
        $second->assertCreated()
            ->assertJsonPath('data.receipt.id', $receiptId)
            ->assertJsonPath('data.receipt.receiptNumber', $receiptNumber);

        $reissue = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments/'.$payment['id'].'/receipt', [
            'reissue' => true,
        ]);

        $reissue->assertCreated()
            ->assertJsonPath('data.receipt.paymentId', $payment['id'])
            ->assertJsonPath('data.receipt.reissuedFromReceiptId', $receiptId);

        $this->assertDatabaseCount('preschool_receipts', 2);
    }

    public function test_student_payment_summary_and_invoice_history_endpoints(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $payment = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 120,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->json('data.payment');

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments/'.$payment['id'].'/receipt')->assertCreated();

        $summary = $this->actingWithToken($context['admin'])->getJson('/api/preschool/students/'.$context['student']->id.'/payment-summary');
        $summary->assertOk()
            ->assertJsonPath('data.summary.invoiceCount', 1)
            ->assertJsonPath('data.summary.receiptCount', 1)
            ->assertJsonPath('data.summary.outstandingBalance', 0);

        $this->actingWithToken($context['admin'])->getJson('/api/preschool/students/'.$context['student']->id.'/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_invoice_print_endpoint_returns_html(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/print');

        $response->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Preschool Invoice', false)
            ->assertSee($invoice->invoice_number, false)
            ->assertSee('Mia Lopez', false)
            ->assertSee('120.00', false);
    }

    public function test_invoice_print_endpoint_handles_missing_optional_relationships(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        DB::table('preschool_invoices')
            ->where('id', $invoice->id)
            ->update([
                'academic_year_id' => null,
                'term_id' => null,
            ]);

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/print');

        $response->assertOk()
            ->assertSee($invoice->invoice_number, false);
    }

    public function test_teacher_cannot_manage_invoices_or_receipts(): void
    {
        $context = $this->createBillingContext();
        $teacher = User::factory()->asTeacherPreschool()->create([
            'status' => 'active',
        ]);

        $this->actingWithToken($teacher)->postJson('/api/preschool/invoices', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_number' => 'INV-TEACHER-001',
            'items' => [
                ['description' => 'Tuition fee', 'quantity' => 1, 'unit_price' => 50],
            ],
        ])->assertForbidden();

        $invoice = $this->createInvoice($context);
        $payment = PreschoolPayment::query()->create([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'payment_reference' => 'PAY-TEACHER-001',
            'amount' => 50,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'due_date' => now()->addDays(10),
            'note' => 'Teacher workflow fixture',
        ]);

        $this->actingWithToken($teacher)->postJson('/api/preschool/payments/'.$payment->id.'/receipt')->assertForbidden();
    }

    public function test_teacher_cannot_delete_invoices(): void
    {
        $context = $this->createBillingContext();
        $teacher = User::factory()->asTeacherPreschool()->create([
            'status' => 'active',
        ]);
        $invoice = $this->createInvoice($context);

        $this->actingWithToken($teacher)->deleteJson('/api/preschool/invoices/'.$invoice->id)
            ->assertForbidden();
    }

    private function createBillingContext(): array
    {
        $admin = User::factory()->asAdminPreschool()->create([
            'status' => 'active',
        ]);

        $year = PreschoolAcademicYear::query()->create([
            'code' => 'AY-2026',
            'label' => 'Academic Year 2026',
            'status' => 'active',
            'is_current' => true,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $term = PreschoolAcademicTerm::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'T1-2026',
            'name' => 'Term 1',
            'status' => 'active',
            'is_current' => true,
            'sort_order' => 1,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
        ]);

        $class = PreschoolClass::query()->create([
            'code' => 'PC-101',
            'name' => 'Sunflower',
            'level' => 'Nursery',
            'students_count' => 1,
            'tuition_fee' => 120,
            'status' => 'active',
        ]);

        $student = PreschoolStudent::query()->create([
            'student_code' => 'PS-10001',
            'first_name' => 'Mia',
            'last_name' => 'Lopez',
            'gender' => 'female',
            'status' => 'active',
            'guardian_name' => 'Ana Lopez',
            'guardian_phone' => '012345678',
        ]);

        $student->classes()->attach($class->id, [
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        return [
            'admin' => $admin,
            'year' => $year,
            'term' => $term,
            'class' => $class,
            'student' => $student,
        ];
    }

    private function createInvoice(array $context): PreschoolInvoice
    {
        return app(PreschoolBillingService::class)->createInvoice([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 0,
            'items' => [
                ['description' => 'Tuition fee', 'quantity' => 1, 'unit_price' => 120, 'sort_order' => 1],
            ],
        ], $context['admin']);
    }
}
