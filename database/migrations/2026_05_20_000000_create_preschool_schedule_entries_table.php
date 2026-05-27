<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preschool schedules start as a compact weekly timetable table so the
     * module can detect overlap conflicts without introducing recurrence or
     * calendar complexity too early.
     */
    public function up(): void
    {
        Schema::create('preschool_schedule_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')
                ->constrained('preschool_classes')
                ->cascadeOnDelete();
            $table->string('teacher_user_id', 16)->nullable()->index();
            $table->unsignedTinyInteger('day_of_week')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 100)->nullable()->index();
            $table->string('activity_label', 255);
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_until')->nullable()->index();
            $table->string('created_by_user_id', 16)->nullable()->index();
            $table->string('updated_by_user_id', 16)->nullable()->index();
            $table->timestamps();

            $table->index(['class_id', 'day_of_week']);
            $table->index(['teacher_user_id', 'day_of_week']);
            $table->index(['day_of_week', 'start_time', 'end_time']);
        });

        Schema::table('preschool_schedule_entries', function (Blueprint $table): void {
            $table->foreign('teacher_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_schedule_entries');
    }
};
