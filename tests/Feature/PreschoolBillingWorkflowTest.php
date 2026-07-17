<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClass;
use App\Models\PreschoolInvoice;
use App\Models\Organization;
use App\Models\PreschoolPayment;
use App\Models\PreschoolReceipt;
use App\Models\PreschoolStudent;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use App\Services\PreschoolBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
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
            ->assertJsonPath('data.payment.paymentStatus', 'paid')
            ->assertJsonPath('data.payment.receiptCount', 1)
            ->assertJsonPath('data.receipt.invoiceId', $invoice->id);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('40.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('80.00', number_format((float) $invoice->balance_due, 2, '.', ''));

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 80,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', number_format((float) $invoice->balance_due, 2, '.', ''));
    }

    public function test_existing_invoice_payment_creates_receipt_and_blocks_overpayment(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $response = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'mode' => 'existing_invoice',
            'student_id' => $context['student']->id,
            'invoice_id' => $invoice->id,
            'amount' => 30,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_reference' => 'PAY-EXISTING-001',
            'paid_at' => now()->toDateString(),
            'note' => 'Existing invoice payment',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.invoiceId', $invoice->id)
            ->assertJsonPath('data.payment.receiptCount', 1)
            ->assertJsonPath('data.receipt.paymentId', $response->json('data.payment.id'));

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('30.00', number_format((float) $invoice->paid_amount, 2, '.', ''));
        $this->assertSame('90.00', number_format((float) $invoice->balance_due, 2, '.', ''));

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'mode' => 'existing_invoice',
            'student_id' => $context['student']->id,
            'invoice_id' => $invoice->id,
            'amount' => 200,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_reference' => 'PAY-EXISTING-002',
            'paid_at' => now()->toDateString(),
            'note' => 'Overpayment attempt',
        ])->assertStatus(422);
    }

    public function test_quick_payment_creates_invoice_payment_and_receipt_atomically(): void
    {
        $context = $this->createBillingContext();

        $response = $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'mode' => 'quick_invoice',
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'description' => 'Quick tuition payment',
            'amount' => 120,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_reference' => 'PAY-QUICK-001',
            'paid_at' => now()->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'note' => 'Quick payment fixture',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.invoice.studentId', $context['student']->id)
            ->assertJsonPath('data.invoice.classId', $context['class']->id)
            ->assertJsonPath('data.payment.invoiceId', $response->json('data.invoice.id'))
            ->assertJsonPath('data.receipt.invoiceId', $response->json('data.invoice.id'));

        $this->assertDatabaseHas('preschool_invoices', [
            'invoice_number' => $response->json('data.invoice.invoiceNumber'),
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('preschool_invoice_items', [
            'invoice_id' => $response->json('data.invoice.id'),
            'description' => 'Quick tuition payment',
            'amount' => 120.0,
        ]);

        $this->assertDatabaseHas('preschool_payments', [
            'payment_reference' => 'PAY-QUICK-001',
            'invoice_id' => $response->json('data.invoice.id'),
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('preschool_receipts', [
            'payment_id' => $response->json('data.payment.id'),
            'invoice_id' => $response->json('data.invoice.id'),
        ]);

        $invoice = PreschoolInvoice::query()->findOrFail($response->json('data.invoice.id'));
        $payment = PreschoolPayment::query()->findOrFail($response->json('data.payment.id'));

        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', number_format((float) $invoice->balance_due, 2, '.', ''));
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame('paid', $payment->payment_status);
    }

    public function test_quick_payment_rolls_back_when_receipt_generation_fails(): void
    {
        $context = $this->createBillingContext();
        $invoiceCountBefore = PreschoolInvoice::query()->count();
        $paymentCountBefore = PreschoolPayment::query()->count();
        $receiptCountBefore = PreschoolReceipt::query()->count();

        app()->instance(PreschoolBillingService::class, new class extends PreschoolBillingService
        {
            public function generateReceipt(PreschoolPayment $payment, ?User $actor = null, bool $forceReissue = false): PreschoolReceipt
            {
                throw new \RuntimeException('Simulated receipt failure.');
            }
        });

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'mode' => 'quick_invoice',
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'description' => 'Rollback tuition',
            'amount' => 120,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_reference' => 'PAY-ROLLBACK-001',
            'paid_at' => now()->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'note' => 'Rollback fixture',
        ])->assertStatus(500);

        $this->assertSame($invoiceCountBefore, PreschoolInvoice::query()->count());
        $this->assertSame($paymentCountBefore, PreschoolPayment::query()->count());
        $this->assertSame($receiptCountBefore, PreschoolReceipt::query()->count());

        $this->assertDatabaseMissing('preschool_invoices', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseMissing('preschool_payments', [
            'payment_reference' => 'PAY-ROLLBACK-001',
        ]);
    }

    public function test_invoice_download_endpoints_return_pdf_and_xlsx(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $pdf = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=pdf');
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition');
        $pdfContent = (string) $pdf->getContent();
        $this->assertStringStartsWith('%PDF', $pdfContent);
        $this->assertStringContainsString('xref', $pdfContent);
        $this->assertStringContainsString('startxref', $pdfContent);
        $this->assertStringContainsString('%%EOF', $pdfContent);

        $xlsx = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=xlsx');
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition');
        $this->assertXlsxWorkbookStructure((string) $xlsx->getContent(), 'Invoice', 1);
    }

    public function test_invoice_xlsx_export_renders_branded_workbook_layout_for_multiple_item_counts(): void
    {
        $context = $this->createBillingContext();

        foreach ([1, 5, 10, 20] as $itemCount) {
            $invoice = $this->createInvoiceWithItemCount($context, $itemCount);
            $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

            $xlsx = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=xlsx');
            $xlsx->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->assertXlsxWorkbookStructure((string) $xlsx->getContent(), 'Invoice', $itemCount);
        }
    }

    public function test_invoice_xlsx_export_handles_khmer_student_names_and_long_invoice_numbers(): void
    {
        $context = $this->createBillingContext();
        $invoice = app(PreschoolBillingService::class)->createInvoice([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-20260717-000000000000123456789',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 0,
            'items' => [
                ['description' => 'Tuition fee', 'quantity' => 1, 'unit_price' => 120, 'sort_order' => 1],
            ],
        ], $context['admin']);

        $invoice->student()->update([
            'first_name' => 'មីយ៉ា',
            'last_name' => 'លូប៉េស',
            'public_id' => 'STU-HFCCF-000000123456789',
        ]);

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=xlsx');
        $response->assertOk();

        $spreadsheet = $this->loadWorkbookFromXlsxContent((string) $response->getContent());
        $sheet = $spreadsheet->getSheetByName('Invoice');

        $this->assertNotNull($sheet);
        $this->assertSame('មីយ៉ា លូប៉េស', (string) $sheet->getCell('C7')->getValue());
        $this->assertSame('STU-HFCCF-000000123456789', (string) $sheet->getCell('C9')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C9')->getDataType());
        $this->assertStringContainsString('INV-20260717-000000000000123456789', (string) $sheet->getCell('H1')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('H1')->getDataType());
    }

    public function test_invoice_xlsx_export_preserves_numeric_zero_values_discount_and_paid_invoice(): void
    {
        $context = $this->createBillingContext();
        $invoice = app(PreschoolBillingService::class)->createInvoice([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-ZERO-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 20,
            'items' => [
                ['description' => 'Scholarship adjustment', 'quantity' => 0, 'unit_price' => 0, 'sort_order' => 1],
                ['description' => 'Balanced tuition', 'quantity' => 1, 'unit_price' => 100, 'sort_order' => 2],
            ],
        ], $context['admin']);

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/payments', [
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'invoice_id' => $invoice->id,
            'amount' => 80,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertCreated();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=xlsx');
        $response->assertOk();

        $spreadsheet = $this->loadWorkbookFromXlsxContent((string) $response->getContent());
        $sheet = $spreadsheet->getSheetByName('Invoice');

        $this->assertNotNull($sheet);
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('G14')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('H14')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('I14')->getDataType());
        $this->assertSame(0.0, (float) $sheet->getCell('G14')->getValue());
        $this->assertSame(0.0, (float) $sheet->getCell('H14')->getValue());
        $this->assertSame(0.0, (float) $sheet->getCell('I14')->getValue());
        $this->assertSame(1.0, (float) $sheet->getCell('G15')->getValue());
        $this->assertSame(100.0, (float) $sheet->getCell('H15')->getValue());
        $this->assertSame(100.0, (float) $sheet->getCell('I15')->getValue());
        $this->assertStringContainsString('Paid', (string) $sheet->getCell('H4')->getValue());
    }

    public function test_invoice_xlsx_export_preserves_draft_and_cancelled_statuses(): void
    {
        $context = $this->createBillingContext();
        $draftInvoice = app(PreschoolBillingService::class)->createInvoice([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-DRAFT-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 0,
            'items' => [
                ['description' => 'Draft tuition', 'quantity' => 1, 'unit_price' => 100, 'sort_order' => 1],
            ],
        ], $context['admin']);

        $draftResponse = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$draftInvoice->id.'/download?format=xlsx');
        $draftResponse->assertOk();
        $draftSheet = $this->loadWorkbookFromXlsxContent((string) $draftResponse->getContent())->getSheetByName('Invoice');
        $this->assertNotNull($draftSheet);
        $this->assertStringContainsString('Draft', (string) $draftSheet->getCell('H4')->getValue());

        $cancelledInvoice = app(PreschoolBillingService::class)->createInvoice([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-CANCELLED-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 0,
            'items' => [
                ['description' => 'Cancelled tuition', 'quantity' => 1, 'unit_price' => 100, 'sort_order' => 1],
            ],
        ], $context['admin']);

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$cancelledInvoice->id.'/cancel')->assertOk();

        $cancelledResponse = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$cancelledInvoice->id.'/download?format=xlsx');
        $cancelledResponse->assertOk();
        $cancelledSheet = $this->loadWorkbookFromXlsxContent((string) $cancelledResponse->getContent())->getSheetByName('Invoice');

        $this->assertNotNull($cancelledSheet);
        $this->assertStringContainsString('Cancelled', (string) $cancelledSheet->getCell('H4')->getValue());
    }

    public function test_invoice_pdf_download_returns_controlled_error_when_browser_binary_is_missing(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        config()->set('services.preschool_pdf.browser_binary', 'Z:\\missing\\chrome.exe');
        Process::fake()->preventStrayProcesses();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=pdf');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Invoice PDF rendering is temporarily unavailable.');

        Process::assertNothingRan();
    }

    public function test_invoice_pdf_download_cleans_up_temp_files_when_browser_process_fails(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        config()->set('services.preschool_pdf.browser_binary', PHP_BINARY);
        File::ensureDirectoryExists(storage_path('app/tmp/preschool-invoices'));
        foreach (File::files(storage_path('app/tmp/preschool-invoices')) as $file) {
            File::delete($file->getPathname());
        }

        Process::fake([
            '*' => Process::result('', 'simulated renderer failure', 1),
        ])->preventStrayProcesses();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=pdf');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Invoice PDF rendering is temporarily unavailable.');

        Process::assertRan(function ($process, $result) {
            $command = $process->command;

            return $result->exitCode() === 1
                && collect($command)->contains(fn ($argument) => str_starts_with($argument, '--user-data-dir='))
                && collect($command)->contains(fn ($argument) => str_starts_with($argument, '--print-to-pdf='));
        });
        $this->assertSame([], File::files(storage_path('app/tmp/preschool-invoices')));

        $xlsx = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=xlsx');
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_invoice_pdf_html_contains_structured_invoice_sections(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);

        $html = app(PreschoolBillingService::class)->renderInvoicePdfHtml($invoice->fresh([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.receipts',
            'receipts',
        ]));

        $this->assertStringContainsString('Hope For Cambodian Children Fund (HFCCF)', $html);
        $this->assertStringContainsString('កម្មវិធីមត្តេយ្យសិក្សា', $html);
        $this->assertStringContainsString('Preschool Program', $html);
        $this->assertStringContainsString('វិក្កយបត្រ', $html);
        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringContainsString('ព័ត៌មានសិស្ស', $html);
        $this->assertStringContainsString('ព័ត៌មានវិក្កយបត្រ', $html);
        $this->assertStringContainsString('បញ្ជីសេវា', $html);
        $this->assertStringContainsString('វិក្កយបត្រនេះត្រូវបានបង្កើតដោយប្រព័ន្ធ HFCCF', $html);
        $this->assertStringNotContainsString('ប្រវត្តិការទូទាត់', $html);
        $this->assertStringNotContainsString('ប្រវត្តិបង្កាន់ដៃ', $html);
        $this->assertStringContainsString((string) $invoice->invoice_number, $html);
        $this->assertStringContainsString('Mia Lopez', $html);
        $this->assertStringContainsString('Tuition fee', $html);
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
            ->assertSee('HFCCF', false)
            ->assertSee($invoice->invoice_number, false)
            ->assertSee('Mia Lopez', false)
            ->assertSee('120.00', false);

        $this->assertStringContainsString('Noto Sans Khmer PDF', (string) $response->getContent());
        $this->assertStringContainsString('Invoice Information', (string) $response->getContent());
        $this->assertStringContainsString('Student Information', (string) $response->getContent());
    }

    public function test_invoice_print_uses_official_logo_fallback_when_organization_logo_is_missing(): void
    {
        $context = $this->createBillingContext();
        $invoice = $this->createInvoice($context);
        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        Organization::query()->where('is_active', true)->update([
            'logo' => null,
        ]);

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/print');

        $response->assertOk();
        $this->assertStringContainsString('data:image/png;base64,', (string) $response->getContent());
    }

    public function test_missing_invoice_download_returns_not_found(): void
    {
        $context = $this->createBillingContext();

        $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/999999/download?format=pdf')
            ->assertNotFound()
            ->assertJsonPath('message', 'Invoice not found.');
    }

    public function test_invoice_pdf_generation_handles_khmer_and_english_content(): void
    {
        $context = $this->createBillingContext();
        $invoice = app(PreschoolBillingService::class)->createInvoice([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-KHMER-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 0,
            'items' => [
                ['description' => 'ថ្លៃសិក្សា Tuition fee', 'quantity' => 1, 'unit_price' => 120, 'sort_order' => 1],
            ],
        ], $context['admin']);

        $invoice->student()->update([
            'first_name' => 'មីយ៉ា',
            'last_name' => 'Lopez',
        ]);

        $this->actingWithToken($context['admin'])->postJson('/api/preschool/invoices/'.$invoice->id.'/issue')->assertOk();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/invoices/'.$invoice->id.'/download?format=pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        app()->setLocale('kh');
        $html = app(PreschoolBillingService::class)->renderInvoicePdfHtml($invoice->fresh([
            'student',
            'preschoolClass',
            'academicYear',
            'term',
            'items',
            'payments.receipts',
            'receipts',
        ]));

        $this->assertStringContainsString('វិក្កយបត្រ', $html);
        $this->assertStringContainsString('ព័ត៌មានសិស្ស', $html);
        $this->assertStringContainsString('មីយ៉ា', $html);
        $this->assertStringContainsString('ថ្លៃសិក្សា Tuition fee', $html);
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
        Organization::query()->create([
            'name' => 'Hope For Cambodian Children Fund (HFCCF)',
            'name_kh' => 'អង្គការ មូលនិធិក្តីសង្ឃឹមនៃកុមារកម្ពុជា',
            'address' => 'Phnom Penh, Cambodia',
            'email' => 'info@hfccf.org',
            'phone' => '+855 23 000 000',
            'is_active' => true,
        ]);

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

    private function createInvoiceWithItemCount(array $context, int $itemCount): PreschoolInvoice
    {
        $items = [];
        for ($index = 1; $index <= $itemCount; $index++) {
            $items[] = [
                'description' => sprintf('Invoice item %d', $index),
                'quantity' => 1,
                'unit_price' => 25 + $index,
                'sort_order' => $index,
            ];
        }

        return app(PreschoolBillingService::class)->createInvoice([
            'student_id' => $context['student']->id,
            'class_id' => $context['class']->id,
            'academic_year_id' => $context['year']->id,
            'term_id' => $context['term']->id,
            'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'discount_amount' => 0,
            'items' => $items,
        ], $context['admin']);
    }

    private function assertXlsxWorkbookStructure(string $content, string $sheetName, int $itemCount): void
    {
        $this->assertStringStartsWith('PK', $content);

        $spreadsheet = $this->loadWorkbookFromXlsxContent($content);
        $this->assertSame([$sheetName], $spreadsheet->getSheetNames());

        $sheet = $spreadsheet->getSheetByName($sheetName);
        $this->assertNotNull($sheet);

        $lastItemRow = 13 + $itemCount;
        $lastRow = $lastItemRow + 11;

        $this->assertSame('Invoice', $sheet->getTitle());
        $this->assertGreaterThan(0, $sheet->getDrawingCollection()->count());
        $this->assertStringContainsString('Invoice Number', (string) $sheet->getCell('H1')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C9')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('A14')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('G14')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('H14')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('I14')->getDataType());
        $this->assertSame(1, (int) $sheet->getCell('A14')->getValue());
        $this->assertSame(1.0, (float) $sheet->getCell('G14')->getValue());
        $this->assertSame(1, (int) $sheet->getPageSetup()->getFitToWidth());
        $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $sheet->getPageSetup()->getOrientation());
        $this->assertSame('A1:J'.$lastRow, $sheet->getPageSetup()->getPrintArea());
        $this->assertSame([13, 13], $sheet->getPageSetup()->getRowsToRepeatAtTop());
        $this->assertStringContainsString('Invoice Date', (string) $sheet->getCell('H2')->getValue());
        $this->assertStringContainsString('Due Date', (string) $sheet->getCell('H3')->getValue());
        $this->assertStringContainsString('Status', (string) $sheet->getCell('H4')->getValue());
        $this->assertSame('Subtotal', (string) $sheet->getCell('F'.($lastItemRow + 2))->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('I'.($lastItemRow + 2))->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('I'.($lastItemRow + 6))->getDataType());
        $this->assertStringStartsWith('Invoice Number: ', (string) $sheet->getCell('A'.$lastRow)->getValue());
    }

    private function loadWorkbookFromXlsxContent(string $content): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'hfccf_invoice_test_');
        $this->assertNotFalse($tempPath);

        $xlsxPath = $tempPath.'.xlsx';
        if (! @rename($tempPath, $xlsxPath)) {
            $xlsxPath = $tempPath;
        }

        File::put($xlsxPath, $content);

        try {
            return IOFactory::load($xlsxPath);
        } finally {
            File::delete($xlsxPath);
            if ($xlsxPath !== $tempPath) {
                File::delete($tempPath);
            }
        }
    }
}

