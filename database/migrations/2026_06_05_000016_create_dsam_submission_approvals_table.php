<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only event log — never updated or deleted
        Schema::create('dsam_submission_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('dsam_form_submissions')
                ->cascadeOnDelete();
            $table->enum('action', [
                'submit', 'send_for_review', 'approve', 'reject', 'reopen', 'archive'
            ]);
            // users.id is string(16)
            $table->string('actor_id', 16);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();

            $table->index('submission_id');
            $table->index(['actor_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsam_submission_approvals');
    }
};
