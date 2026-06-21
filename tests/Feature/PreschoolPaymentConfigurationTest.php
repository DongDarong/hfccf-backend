<?php

namespace Tests\Feature;

use App\Models\PreschoolFeeType;
use App\Models\PreschoolPaymentMethod;
use App\Models\User;
use App\Support\PreschoolPaymentConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolPaymentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_get_default_settings(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/settings/payments');

        $response->assertOk()
            ->assertJsonPath('data.settings.invoice_prefix', 'INV')
            ->assertJsonPath('data.settings.receipt_prefix', 'RCT')
            ->assertJsonPath('data.settings.grace_period_days', 5)
            ->assertJsonPath('data.settings.late_fee_enabled', true);
    }

    public function test_adminpreschool_can_update_settings(): void
    {
        Sanctum::actingAs($this->makeUser('adminpreschool'));

        $response = $this->putJson('/api/preschool/settings/payments', [
            'invoice_prefix' => 'INVX',
            'receipt_prefix' => 'RCTX',
            'next_invoice_number' => 25,
            'next_receipt_number' => 41,
            'late_fee_enabled' => true,
            'late_fee_type' => 'percentage',
            'late_fee_amount' => 5,
            'grace_period_days' => 7,
            'proration_enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.settings.invoice_prefix', 'INVX')
            ->assertJsonPath('data.settings.receipt_prefix', 'RCTX')
            ->assertJsonPath('data.settings.grace_period_days', 7);
    }

    public function test_teacher_preschool_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('teacher-preschool'));

        $this->getJson('/api/preschool/settings/payments')->assertForbidden();
        $this->putJson('/api/preschool/settings/payments', $this->settingsPayload())->assertForbidden();
    }

    public function test_unrelated_admin_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('adminenglish'));

        $this->getJson('/api/preschool/settings/payments')->assertForbidden();
    }

    public function test_fee_type_create_update_archive(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $created = $this->postJson('/api/preschool/settings/payments/fee-types', [
            'name' => 'Snack Fee',
            'code' => 'snack_fee',
            'description' => 'Daily snack',
            'default_amount' => 12.5,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 10,
        ])->assertCreated();

        $feeTypeId = $created->json('data.fee_type.id');

        $this->putJson("/api/preschool/settings/payments/fee-types/{$feeTypeId}", [
            'name' => 'Snack Fee Updated',
            'code' => 'snack_fee',
            'description' => 'Updated daily snack',
            'default_amount' => 14.25,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 11,
        ])->assertOk();

        $this->postJson("/api/preschool/settings/payments/fee-types/{$feeTypeId}/archive")->assertOk();

        $this->assertSoftDeleted('preschool_fee_types', [
            'id' => $feeTypeId,
        ]);
    }

    public function test_payment_method_create_update_archive(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $created = $this->postJson('/api/preschool/settings/payments/payment-methods', [
            'name' => 'Mobile Money',
            'code' => 'mobile_money',
            'description' => 'Mobile wallet',
            'is_active' => true,
            'sort_order' => 10,
        ])->assertCreated();

        $methodId = $created->json('data.payment_method.id');

        $this->putJson("/api/preschool/settings/payments/payment-methods/{$methodId}", [
            'name' => 'Mobile Money Updated',
            'code' => 'mobile_money',
            'description' => 'Updated mobile wallet',
            'is_active' => true,
            'sort_order' => 11,
        ])->assertOk();

        $this->postJson("/api/preschool/settings/payments/payment-methods/{$methodId}/archive")->assertOk();

        $this->assertSoftDeleted('preschool_payment_methods', [
            'id' => $methodId,
        ]);
    }

    public function test_billing_rules_update(): void
    {
        Sanctum::actingAs($this->makeUser('adminpreschool'));

        $this->putJson('/api/preschool/settings/payments/billing-rules', [
            'rules' => [
                [
                    'rule_name' => 'Grace Period Days',
                    'rule_code' => 'grace_period_days',
                    'rule_value' => '9',
                    'description' => 'Updated grace period',
                    'is_active' => true,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('preschool_billing_rules', [
            'rule_code' => 'grace_period_days',
            'rule_value' => '9',
        ]);
    }

    public function test_invoice_and_receipt_number_generation_and_late_fee_calculation(): void
    {
        $service = app(PreschoolPaymentConfigurationService::class);

        $invoice = $service->generateNextInvoiceNumber();
        $receipt = $service->generateNextReceiptNumber();

        $this->assertMatchesRegularExpression('/^INV-\d{4}-00001$/', $invoice);
        $this->assertMatchesRegularExpression('/^RCT-\d{4}-00001$/', $receipt);
        $this->assertSame(2, $service->getSettings()->next_invoice_number);
        $this->assertSame(2, $service->getSettings()->next_receipt_number);

        $service->updateSettings([
            'late_fee_enabled' => true,
            'late_fee_type' => 'percentage',
            'late_fee_amount' => 5,
            'grace_period_days' => 5,
        ]);

        $this->assertTrue($service->shouldApplyLateFee('2026-06-01', '2026-06-10'));
        $this->assertSame(5.0, $service->calculateLateFee(100));
    }

    public function test_dashboard_payment_summary_returns_live_values(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/settings/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.payments.fee_types_count', 6)
            ->assertJsonPath('data.dashboard.payments.payment_methods_count', 5)
            ->assertJsonPath('data.dashboard.payments.late_fee_enabled', true)
            ->assertJsonPath('data.dashboard.payments.grace_period_days', 5)
            ->assertJsonPath('data.dashboard.payments.invoice_prefix', 'INV')
            ->assertJsonPath('data.dashboard.payments.receipt_prefix', 'RCT');
    }

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'email' => uniqid($roleCode.'_', true).'@example.test',
            'password' => 'password',
            'role_code' => $roleCode,
        ]);
    }

    private function settingsPayload(): array
    {
        return [
            'invoice_prefix' => 'INV',
            'receipt_prefix' => 'RCT',
            'next_invoice_number' => 1,
            'next_receipt_number' => 1,
            'late_fee_enabled' => true,
            'late_fee_type' => 'fixed',
            'late_fee_amount' => 5,
            'grace_period_days' => 5,
            'proration_enabled' => false,
        ];
    }
}
