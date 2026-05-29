<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_report_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('period_label', 120);
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('status', 32)->default('draft');
            $table->json('summary_snapshot')->nullable();
            $table->json('report_snapshot')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by', 16)->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('finalized_by', 16)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_by', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('period_label', 'preschool_report_periods_period_label_index');
            $table->index('academic_year_id', 'preschool_report_periods_academic_year_id_index');
            $table->index('term_id', 'preschool_report_periods_term_id_index');
            $table->index('status', 'preschool_report_periods_status_index');

            $table->unique(['period_label', 'academic_year_id', 'term_id'], 'preschool_report_periods_period_context_unique');

            $table->foreign('academic_year_id')
                ->references('id')
                ->on('preschool_academic_years')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('term_id')
                ->references('id')
                ->on('preschool_terms')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('locked_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('finalized_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('archived_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_report_periods');
    }
};
