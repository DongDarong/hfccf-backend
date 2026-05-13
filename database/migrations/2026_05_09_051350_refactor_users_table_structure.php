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

            // 3. Add Audit Fields
            $table->string('created_by', 16)->nullable()->after('created_at');
            $table->string('updated_by', 16)->nullable()->after('updated_at');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();

            // 4. Add Optimized Indexes
            $table->index(['email', 'status'], 'users_email_status_index');
            $table->index(['username', 'status'], 'users_username_status_index');
        });

        // 2. Resolve Soft Delete / Email Unique conflict (MySQL/MariaDB only).
        // SQLite (test DB) does not support MySQL's IF() virtual expression.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                // Drop existing unique email index.
                $table->dropUnique(['email']);

                // Enforce uniqueness only for non-deleted users (MySQL 5.7.6+ / MariaDB 10.2.2+).
                $table->string('active_email_only')->virtualAs('IF(deleted_at IS NULL, email, NULL)')->nullable();
                $table->unique('active_email_only', 'users_active_email_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('users', function (Blueprint $table) use ($driver) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex('users_username_status_index');
            $table->dropIndex('users_email_status_index');

            if ($driver !== 'sqlite') {
                $table->dropUnique('users_active_email_unique');
                $table->dropColumn('active_email_only');
            }

            $table->dropColumn(['created_by', 'updated_by']);

            $table->dropUnique(['username']);

            if ($driver !== 'sqlite') {
                $table->unique('email');
            }
        });
    }
};
