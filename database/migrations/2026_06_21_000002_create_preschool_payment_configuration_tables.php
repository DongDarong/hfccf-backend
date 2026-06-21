<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_payment_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_prefix', 32)->default('INV');
            $table->string('receipt_prefix', 32)->default('RCT');
            $table->unsignedBigInteger('next_invoice_number')->default(1);
            $table->unsignedBigInteger('next_receipt_number')->default(1);
            $table->boolean('late_fee_enabled')->default(true);
            $table->string('late_fee_type', 32)->default('fixed');
            $table->decimal('late_fee_amount', 12, 2)->default(5.00);
            $table->unsignedSmallInteger('grace_period_days')->default(5);
            $table->boolean('proration_enabled')->default(false);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_fee_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'preschool_fee_types_active_sort_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('code', 64)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'preschool_payment_methods_active_sort_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_billing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_name', 191);
            $table->string('rule_code', 64)->unique();
            $table->string('rule_value', 191);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_billing_rules');
        Schema::dropIfExists('preschool_payment_methods');
        Schema::dropIfExists('preschool_fee_types');
        Schema::dropIfExists('preschool_payment_settings');
    }
};
