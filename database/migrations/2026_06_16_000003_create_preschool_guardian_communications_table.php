<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preschool_guardian_communications')) {
            return;
        }

        Schema::create('preschool_guardian_communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('preschool_students')->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('preschool_guardians')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('communication_type');
            $table->string('channel');
            $table->string('subject');
            $table->text('message');
            $table->string('severity')->default('medium');
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('created_by', 16)->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at'], 'preschool_guardian_communications_student_created_index');
            $table->index(['guardian_id', 'created_at'], 'preschool_guardian_communications_guardian_created_index');
            $table->index(['source_type', 'source_id'], 'preschool_guardian_communications_source_index');
            $table->index(['communication_type', 'status'], 'preschool_guardian_communications_type_status_index');
            $table->unique(
                ['student_id', 'guardian_id', 'source_type', 'source_id', 'communication_type', 'channel'],
                'preschool_guardian_communications_dedupe_unique'
            );

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_guardian_communications');
    }
};
