<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds academic-context and description columns to two tables.
 *
 * preschool_payments
 * ------------------
 * The auto-enrollment payment feature requires payments to carry academic year
 * and term context so the payments UI can group and filter by period. A
 * human-readable description is added for display in receipts and admin tables.
 * The created_by column records which admin triggered the enrollment. All four
 * columns are additive — no existing data or queries are affected.
 *
 * preschool_classes
 * -----------------
 * Adding tuition_fee to the class record provides the source of truth for the
 * term fee charged to students enrolled in that class. The column is nullable
 * so existing classes are unaffected until an admin populates the value.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     *
     * Adds four columns to preschool_payments and one column to preschool_classes.
     * Two FK constraints on preschool_payments reference existing academic tables;
     * both are set to nullOnDelete so a year/term deletion does not orphan rows.
     *
     * @return void
     */
    public function up(): void
    {
        // ── preschool_payments ─────────────────────────────────────────────────
        // Guards prevent duplicate-column errors if the migration was partially
        // applied in a prior failed attempt and the table was not rolled back.
        Schema::table('preschool_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_payments', 'academic_year_id')) {
                // FK to the academic year this payment belongs to — nullable so
                // manually-created payments without a year context still save.
                $table->unsignedBigInteger('academic_year_id')
                    ->nullable()
                    ->after('class_id');

                // Nullify the year link on deletion rather than cascade-delete
                // payment rows (payments are financial records and must be preserved).
                $table->foreign('academic_year_id')
                    ->references('id')
                    ->on('preschool_academic_years')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('preschool_payments', 'term_id')) {
                // FK to the term this payment covers.
                $table->unsignedBigInteger('term_id')
                    ->nullable()
                    ->after('academic_year_id');

                $table->foreign('term_id')
                    ->references('id')
                    ->on('preschool_terms')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('preschool_payments', 'description')) {
                // Human-readable description shown on receipts and admin tables.
                // "note" already exists and is internal; description is display-facing.
                $table->text('description')->nullable()->after('note');
            }

            if (! Schema::hasColumn('preschool_payments', 'created_by')) {
                // The admin user who created the record (varchar(16) to match
                // users.id which is varchar(16) — no FK constraint added).
                $table->string('created_by', 16)->nullable()->after('description');
            }
        });

        // ── preschool_classes ──────────────────────────────────────────────────
        Schema::table('preschool_classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_classes', 'tuition_fee')) {
                // Per-class tuition fee used when auto-creating a payment on enrollment.
                // Nullable so existing class records do not need immediate data population;
                // admins can fill this before the next enrollment cycle.
                $table->decimal('tuition_fee', 10, 2)
                    ->nullable()
                    ->after('students_count')
                    ->comment('Term tuition charged per enrolled student');
            }
        });
    }

    /**
     * Reverse the migration.
     *
     * Drops FK constraints first (required before dropping columns),
     * then removes the four payment columns and the one class column.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('preschool_payments', function (Blueprint $table): void {
            $table->dropForeign(['academic_year_id']);
            $table->dropForeign(['term_id']);
            $table->dropColumn(['academic_year_id', 'term_id', 'description', 'created_by']);
        });

        Schema::table('preschool_classes', function (Blueprint $table): void {
            $table->dropColumn('tuition_fee');
        });
    }
};
