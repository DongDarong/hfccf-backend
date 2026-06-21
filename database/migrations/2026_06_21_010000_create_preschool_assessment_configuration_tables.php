<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_assessment_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('passing_score')->default(60);
            $table->enum('grading_scale_type', ['percentage', 'letter', 'custom'])->default('letter');
            $table->boolean('weighting_enabled')->default(false);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_assessment_grading_scales', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50);
            $table->string('grade', 20);
            $table->decimal('minimum_score', 5, 2);
            $table->decimal('maximum_score', 5, 2);
            $table->string('color', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_passing')->default(false);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->unique(['grade'], 'preschool_assessment_grading_scales_grade_unique');
            $table->index(['sort_order', 'minimum_score'], 'preschool_assessment_grading_scales_sort_min_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::table('preschool_assessment_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('preschool_assessment_categories', 'created_by')) {
                $table->string('created_by', 16)->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('preschool_assessment_categories', 'updated_by')) {
                $table->string('updated_by', 16)->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('preschool_assessment_categories', 'deleted_at')) {
                $table->softDeletes();
            }

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_assessment_report_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')
                ->constrained('preschool_academic_years')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('term_id')->nullable()
                ->constrained('preschool_terms')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('name', 191);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year_id', 'term_id', 'is_active'], 'preschool_assessment_report_periods_context_index');
            $table->index(['start_date', 'end_date'], 'preschool_assessment_report_periods_dates_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('preschool_assessment_weights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('preschool_assessment_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('percentage', 5, 2);
            $table->string('created_by', 16)->nullable();
            $table->string('updated_by', 16)->nullable();
            $table->timestamps();

            $table->unique(['category_id'], 'preschool_assessment_weights_category_unique');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        DB::table('preschool_assessment_grading_scales')->insert([
            ['name' => 'Excellent', 'grade' => 'A', 'minimum_score' => 90, 'maximum_score' => 100, 'color' => '#16a34a', 'sort_order' => 10, 'is_passing' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Very Good', 'grade' => 'B', 'minimum_score' => 80, 'maximum_score' => 89, 'color' => '#22c55e', 'sort_order' => 20, 'is_passing' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Good', 'grade' => 'C', 'minimum_score' => 70, 'maximum_score' => 79, 'color' => '#eab308', 'sort_order' => 30, 'is_passing' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Developing', 'grade' => 'D', 'minimum_score' => 60, 'maximum_score' => 69, 'color' => '#f97316', 'sort_order' => 40, 'is_passing' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Needs Support', 'grade' => 'F', 'minimum_score' => 0, 'maximum_score' => 59, 'color' => '#ef4444', 'sort_order' => 50, 'is_passing' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_assessment_weights');
        Schema::dropIfExists('preschool_assessment_report_periods');

        Schema::table('preschool_assessment_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('preschool_assessment_categories', 'updated_by')) {
                $table->dropForeign(['updated_by']);
            }

            if (Schema::hasColumn('preschool_assessment_categories', 'created_by')) {
                $table->dropForeign(['created_by']);
            }

            if (Schema::hasColumn('preschool_assessment_categories', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('preschool_assessment_categories', 'updated_by')) {
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('preschool_assessment_categories', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });

        Schema::dropIfExists('preschool_assessment_grading_scales');
        Schema::dropIfExists('preschool_assessment_settings');
    }
};
