<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep assessment data normalized and additive so Preschool progress can
     * grow into reports later without rewriting the existing CRUD tables.
     */
    public function up(): void
    {
        Schema::create('preschool_assessment_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'preschool_assessment_categories_active_sort_index');
        });

        Schema::create('preschool_student_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('class_id')
                ->nullable()
                ->constrained('preschool_classes')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('category_id')
                ->constrained('preschool_assessment_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('assessed_by_user_id', 16);
            $table->string('period_label', 100);
            $table->date('assessment_date');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('rating', 50)->nullable();
            $table->text('observation')->nullable();
            $table->text('teacher_comment')->nullable();
            $table->enum('status', ['draft', 'finalized', 'archived'])->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->string('finalized_by_user_id', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'assessment_date'], 'preschool_student_assessments_student_date_index');
            $table->index(['class_id', 'assessment_date'], 'preschool_student_assessments_class_date_index');
            $table->index(['category_id', 'status'], 'preschool_student_assessments_category_status_index');
            $table->index(['status', 'assessment_date'], 'preschool_student_assessments_status_date_index');
            $table->index('assessed_by_user_id', 'preschool_student_assessments_assessed_by_index');
            $table->index('finalized_by_user_id', 'preschool_student_assessments_finalized_by_index');

            $table->foreign('assessed_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('finalized_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // Default categories keep the first API response useful without a seed
        // dependency and give the UI a stable code set for EN/KH translation.
        DB::table('preschool_assessment_categories')->insert([
            [
                'code' => 'learning_progress',
                'name' => 'Learning Progress',
                'description' => 'Academic understanding, curiosity, and skill growth.',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'behavior',
                'name' => 'Behavior',
                'description' => 'Self-regulation, cooperation, and classroom conduct.',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'attendance_notes',
                'name' => 'Attendance Notes',
                'description' => 'Attendance-related observations and patterns.',
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'classroom_participation',
                'name' => 'Classroom Participation',
                'description' => 'Engagement during learning activities and group work.',
                'sort_order' => 40,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'health_wellbeing',
                'name' => 'Health & Wellbeing',
                'description' => 'Wellbeing, energy, and health-related observations.',
                'sort_order' => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'teacher_comment',
                'name' => 'Teacher Comment',
                'description' => 'General teacher reflection and next-step guidance.',
                'sort_order' => 60,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_student_assessments');
        Schema::dropIfExists('preschool_assessment_categories');
    }
};
