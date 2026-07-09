<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_report_periods', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_report_periods', 'period_type')) {
                $table->string('period_type', 32)->default('term')->after('period_label');
            }
        });

        Schema::table('preschool_assessment_report_periods', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_assessment_report_periods', 'period_type')) {
                $table->string('period_type', 32)->default('term')->after('academic_year_id');
            }
        });

        DB::table('preschool_report_periods')
            ->whereNull('period_type')
            ->update(['period_type' => 'term']);

        DB::table('preschool_assessment_report_periods')
            ->whereNull('period_type')
            ->update(['period_type' => 'term']);

        Schema::table('preschool_report_periods', function (Blueprint $table): void {
            $table->dropUnique('preschool_report_periods_period_context_unique');
            $table->unique(['period_label', 'period_type', 'academic_year_id', 'term_id'], 'preschool_report_periods_period_context_unique');
            $table->index(['period_type', 'academic_year_id', 'term_id', 'status'], 'preschool_report_periods_type_context_status_index');
        });

        Schema::table('preschool_assessment_report_periods', function (Blueprint $table): void {
            $table->index(['period_type', 'academic_year_id', 'term_id', 'is_active'], 'preschool_assessment_report_periods_type_context_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('preschool_assessment_report_periods', function (Blueprint $table): void {
            $table->dropIndex('preschool_assessment_report_periods_type_context_active_index');
            if (Schema::hasColumn('preschool_assessment_report_periods', 'period_type')) {
                $table->dropColumn('period_type');
            }
        });

        Schema::table('preschool_report_periods', function (Blueprint $table): void {
            $table->dropIndex('preschool_report_periods_type_context_status_index');
            $table->dropUnique('preschool_report_periods_period_context_unique');
            $table->unique(['period_label', 'academic_year_id', 'term_id'], 'preschool_report_periods_period_context_unique');

            if (Schema::hasColumn('preschool_report_periods', 'period_type')) {
                $table->dropColumn('period_type');
            }
        });
    }
};
