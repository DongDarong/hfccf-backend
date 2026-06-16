<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('class_id')
                ->constrained('preschool_classes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('preschool_academic_years')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('term_id')
                ->nullable()
                ->constrained('preschool_terms')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('invoice_number', 100)->unique();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->enum('status', ['draft', 'issued', 'partial', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status'], 'preschool_invoices_student_status_index');
            $table->index(['class_id', 'status'], 'preschool_invoices_class_status_index');
            $table->index(['status', 'due_date'], 'preschool_invoices_status_due_index');
        });

        Schema::create('preschool_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('preschool_invoices')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->text('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order'], 'preschool_invoice_items_invoice_sort_index');
        });

        Schema::create('preschool_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('preschool_payments')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('preschool_invoices')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('reissued_from_receipt_id')
                ->nullable()
                ->constrained('preschool_receipts')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('receipt_number', 100)->unique();
            $table->timestamp('issued_at')->nullable();
            $table->string('issued_by', 16)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_id', 'issued_at'], 'preschool_receipts_payment_issued_index');
            $table->index(['invoice_id', 'issued_at'], 'preschool_receipts_invoice_issued_index');
        });

        Schema::table('preschool_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_payments', 'invoice_id')) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('class_id')
                    ->constrained('preschool_invoices')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('preschool_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_payments', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
        });

        Schema::dropIfExists('preschool_receipts');
        Schema::dropIfExists('preschool_invoice_items');
        Schema::dropIfExists('preschool_invoices');
    }
};
