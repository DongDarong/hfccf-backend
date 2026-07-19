<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link existing PreschoolStudentAssessment records to the new monthly submission aggregate.
     *
     * monthly_submission_id remains NULLABLE in Phase A.2 to support:
     * - Legacy assessment records without submissions (backward compatibility)
     * - Gradual migration of existing data
     * - Safe rollback (no orphaned records if migration fails)
     *
     * Will be hardened to NOT NULL in Phase A.3 after:
     * - All eligible legacy records are grouped
     * - Orphan records are reported
     * - Duplicate conflicts are resolved
     */
    public function up(): void
    {
        Schema::table('preschool_student_assessments', function (Blueprint $table): void {
            // Add nullable foreign key
            $table->foreignId('monthly_submission_id')
                ->nullable()
                ->after('id')
                ->constrained('preschool_monthly_submissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Add unique constraint for child records
            $table->unique(
                ['monthly_submission_id', 'student_id'],
                'unique_student_per_monthly_submission'
            );

            // Index for efficient queries
            $table->index(['monthly_submission_id', 'status'], 'idx_submission_status');
        });
    }

    public function down(): void
    {
        Schema::table('preschool_student_assessments', function (Blueprint $table): void {
            $table->dropUnique('unique_student_per_monthly_submission');
            $table->dropIndex('idx_submission_status');
            $table->dropForeign(['monthly_submission_id']);
            $table->dropColumn('monthly_submission_id');
        });
    }
};
