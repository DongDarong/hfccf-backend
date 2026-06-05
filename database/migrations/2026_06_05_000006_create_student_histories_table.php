<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('preschool_students')
                ->cascadeOnDelete();
            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->nullable()
                ->constrained('schools')
                ->nullOnDelete();
            $table->string('grade', 20)->nullable();
            $table->string('class_name', 100)->nullable();
            $table->enum('status', ['active', 'graduated', 'dropped', 'transferred', 'suspended'])
                ->default('active');
            $table->text('notes')->nullable();
            // users.id is string(16) — cannot use foreignId()
            $table->string('recorded_by', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['student_id', 'academic_year_id']);
            $table->index('academic_year_id');
            $table->index('status');

            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_histories');
    }
};
