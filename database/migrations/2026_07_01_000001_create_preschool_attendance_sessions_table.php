<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('preschool_class_id')->constrained('preschool_classes')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('preschool_schedule_entries')->nullOnDelete();
            $table->date('attendance_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('status', 20)->default('open');
            $table->boolean('generated_from_schedule')->default(false);
            $table->text('notes')->nullable();
            $table->string('session_key', 191)->unique();
            $table->string('created_by', 50)->nullable();
            $table->string('closed_by', 50)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['preschool_class_id', 'attendance_date'], 'preschool_attendance_sessions_class_date_index');
            $table->index(['schedule_id', 'attendance_date'], 'preschool_attendance_sessions_schedule_date_index');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_attendance_sessions');
    }
};
