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
        Schema::table('users', function (Blueprint $table) {
            // 1. Add Unique constraint to username
            $table->string('username', 191)->unique()->change();

            // 2. Resolve Soft Delete / Email Unique conflict
            // Drop existing unique email index
            $table->dropUnique(['email']);
            
            // Create a virtual column for active email to enforce uniqueness only for non-deleted users
            // This works in MySQL 5.7.6+ and MariaDB 10.2.2+
            $table->string('active_email_only')->virtualAs('IF(deleted_at IS NULL, email, NULL)')->nullable();
            $table->unique('active_email_only', 'users_active_email_unique');

            // 3. Add Audit Fields
            $table->string('created_by', 16)->nullable()->after('created_at');
            $table->string('updated_by', 16)->nullable()->after('updated_at');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();

            // 4. Add Optimized Indexes
            $table->index(['email', 'status'], 'users_email_status_index');
            $table->index(['username', 'status'], 'users_username_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex('users_username_status_index');
            $table->dropIndex('users_email_status_index');
            $table->dropUnique('users_active_email_unique');
            $table->dropColumn('active_email_only');
            $table->dropColumn(['created_by', 'updated_by']);
            
            $table->dropUnique(['username']);
            $table->unique('email');
        });
    }
};
