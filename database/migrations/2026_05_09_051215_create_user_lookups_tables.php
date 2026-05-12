<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create Lookup Tables
        Schema::create('user_statuses', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('name', 100);
            $table->string('description')->nullable();
        });

        Schema::create('role_scopes', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('name', 100);
        });

        Schema::create('domain_codes', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('name', 100);
        });

        // 2. Seed Lookup Tables
        DB::table('user_statuses')->insert([
            ['code' => 'active', 'name' => 'Active', 'description' => 'User has full access to the system.'],
            ['code' => 'pending', 'name' => 'Pending', 'description' => 'User is awaiting approval or email verification.'],
            ['code' => 'inactive', 'name' => 'Inactive', 'description' => 'User account is disabled.'],
            ['code' => 'suspended', 'name' => 'Suspended', 'description' => 'User account is locked due to security or policy reasons.'],
        ]);

        DB::table('role_scopes')->insert([
            ['code' => 'super_admin', 'name' => 'Super Admin'],
            ['code' => 'admin', 'name' => 'Admin'],
            ['code' => 'staff', 'name' => 'Staff'],
        ]);

        DB::table('domain_codes')->insert([
            ['code' => 'global', 'name' => 'Global'],
            ['code' => 'english', 'name' => 'English'],
            ['code' => 'preschool', 'name' => 'Preschool'],
            ['code' => 'scholarship', 'name' => 'Scholarship'],
            ['code' => 'sport', 'name' => 'Sport'],
        ]);

        // 3. Update Roles Table (scope and domain_code)
        Schema::table('roles', function (Blueprint $table) {
            // First drop the enum indexes if any (usually Laravel doesn't create named ones for enums unless specified)
            // But we need to change the column types.
            $table->string('scope', 32)->change();
            $table->string('domain_code', 32)->change();

            $table->foreign('scope')->references('code')->on('role_scopes')->cascadeOnUpdate();
            $table->foreign('domain_code')->references('code')->on('domain_codes')->cascadeOnUpdate();
        });

        // 4. Update Users Table (status)
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)->change();
            $table->foreign('status')->references('code')->on('user_statuses')->cascadeOnUpdate();
        });

        // 5. Update Deleted Users Table (status)
        Schema::table('deleted_users', function (Blueprint $table) {
            $table->string('status', 32)->change();
            // We don't necessarily need a FK on the archive table to allow for historical statuses,
            // but it's good for consistency if we want to enforce it.
            // For now, just changing from enum to string is enough to match lookups.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['status']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['scope']);
            $table->dropForeign(['domain_code']);
        });

        Schema::dropIfExists('domain_codes');
        Schema::dropIfExists('role_scopes');
        Schema::dropIfExists('user_statuses');
    }
};
