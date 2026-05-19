<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_user_id', 16)->nullable();
            $table->string('domain', 64);
            $table->string('action', 64);
            $table->string('entity_type', 191);
            $table->string('entity_id', 64)->nullable();
            $table->string('entity_label', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_user_id', 'audit_logs_actor_user_id_index');
            $table->index('domain', 'audit_logs_domain_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index('entity_type', 'audit_logs_entity_type_index');
            $table->index('entity_id', 'audit_logs_entity_id_index');
            $table->index('created_at', 'audit_logs_created_at_index');

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
