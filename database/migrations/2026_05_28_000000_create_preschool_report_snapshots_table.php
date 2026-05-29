<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_type', 64);
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('report_period_id')->nullable();
            $table->string('generated_by', 16)->nullable();
            $table->string('lifecycle_state', 32)->default('finalized');
            $table->unsignedInteger('snapshot_version')->default(1);
            $table->json('snapshot_payload');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index('snapshot_type', 'preschool_report_snapshots_snapshot_type_index');
            $table->index('student_id', 'preschool_report_snapshots_student_id_index');
            $table->index('class_id', 'preschool_report_snapshots_class_id_index');
            $table->index('academic_year_id', 'preschool_report_snapshots_academic_year_id_index');
            $table->index('term_id', 'preschool_report_snapshots_term_id_index');
            $table->index('report_period_id', 'preschool_report_snapshots_report_period_id_index');
            $table->index('lifecycle_state', 'preschool_report_snapshots_lifecycle_state_index');

            $table->unique(
                ['snapshot_type', 'student_id', 'class_id', 'academic_year_id', 'term_id', 'report_period_id', 'snapshot_version'],
                'preschool_report_snapshots_context_version_unique',
            );

            $table->foreign('student_id')
                ->references('id')
                ->on('preschool_students')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('class_id')
                ->references('id')
                ->on('preschool_classes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

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

            $table->foreign('report_period_id')
                ->references('id')
                ->on('preschool_report_periods')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('generated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_report_snapshots');
    }
};
