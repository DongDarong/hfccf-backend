<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('preschool_class_teacher_assignments');

        Schema::create('preschool_class_teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('preschool_classes')->restrictOnDelete();
            $table->string('teacher_user_id', 16)->nullable()->index();
            $table->string('teacher_display_name')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'status']);
            $table->index(['teacher_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_class_teacher_assignments');
    }
};
