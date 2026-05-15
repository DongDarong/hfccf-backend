<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['info', 'success', 'warning', 'error', 'system']);
            $table->string('title', 255);
            $table->text('message');
            $table->enum('module', ['global', 'english', 'preschool', 'scholarship', 'sport']);
            $table->string('action_url', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('type', 'notifications_type_index');
            $table->index('module', 'notifications_module_index');
            $table->index('created_by', 'notifications_created_by_index');
            $table->index('created_at', 'notifications_created_at_index');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('notification_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->enum('target_type', ['all', 'role', 'department', 'module', 'user']);
            $table->string('target_value', 191)->nullable();

            $table->index(['notification_id', 'target_type'], 'notification_targets_notification_type_index');
            $table->index('target_type', 'notification_targets_target_type_index');
            $table->index('target_value', 'notification_targets_target_value_index');
        });

        Schema::create('notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('user_id', 16);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['notification_id', 'user_id'], 'notification_recipients_notification_user_unique');
            $table->index('user_id', 'notification_recipients_user_index');
            $table->index('read_at', 'notification_recipients_read_at_index');
            $table->index('dismissed_at', 'notification_recipients_dismissed_at_index');
            $table->index('created_at', 'notification_recipients_created_at_index');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notification_targets');
        Schema::dropIfExists('notifications');
    }
};
