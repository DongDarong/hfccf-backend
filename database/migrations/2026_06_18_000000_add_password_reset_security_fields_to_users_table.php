<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('last_password_reset_at')->nullable()->after('password_changed_at');
            $table->string('last_password_reset_by', 16)->nullable()->after('last_password_reset_at');

            $table->index('must_change_password', 'users_must_change_password_index');
            $table->index('password_changed_at', 'users_password_changed_at_index');
            $table->index('last_password_reset_at', 'users_last_password_reset_at_index');
            $table->index('last_password_reset_by', 'users_last_password_reset_by_index');

            $table->foreign('last_password_reset_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['last_password_reset_by']);
            $table->dropIndex('users_must_change_password_index');
            $table->dropIndex('users_password_changed_at_index');
            $table->dropIndex('users_last_password_reset_at_index');
            $table->dropIndex('users_last_password_reset_by_index');
            $table->dropColumn([
                'must_change_password',
                'password_changed_at',
                'last_password_reset_at',
                'last_password_reset_by',
            ]);
        });
    }
};
