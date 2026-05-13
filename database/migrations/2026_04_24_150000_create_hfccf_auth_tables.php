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
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departments');

        Schema::enableForeignKeyConstraints();

        Schema::create('departments', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('name', 100);
            $table->unsignedTinyInteger('display_order');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index('is_active', 'departments_is_active_index');
            $table->index('display_order', 'departments_display_order_index');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('name', 100);
            $table->enum('scope', ['super_admin', 'admin', 'staff']);
            $table->enum('domain_code', ['global', 'english', 'preschool', 'scholarship', 'sport']);
            $table->string('department_code', 32);
            $table->unsignedTinyInteger('sort_order');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index('scope', 'roles_scope_index');
            $table->index('domain_code', 'roles_domain_code_index');
            $table->index('department_code', 'roles_department_code_index');
            $table->foreign('department_code', 'fk_roles_department')
                ->references('code')
                ->on('departments')
                ->cascadeOnUpdate();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->string('code', 64)->primary();
            $table->string('name', 120);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->string('role_code', 32);
            $table->string('permission_code', 64);
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->primary(['role_code', 'permission_code']);
            $table->foreign('role_code', 'fk_role_permissions_role')
                ->references('code')
                ->on('roles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('permission_code', 'fk_role_permissions_permission')
                ->references('code')
                ->on('permissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 16)->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('username', 191);
            $table->string('email', 191)->unique();
            $table->string('phone', 32)->nullable();
            $table->string('role_code', 32);
            $table->string('department_code', 32);
            $table->text('bio')->nullable();
            $table->enum('status', ['active', 'pending', 'inactive', 'suspended'])->default('active');
            $table->string('avatar', 2048)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->index('role_code', 'users_role_code_index');
            $table->index('status', 'users_status_index');
            $table->index('department_code', 'users_department_code_index');
            $table->index('deleted_at', 'users_deleted_at_index');
            $table->foreign('role_code', 'fk_users_role')
                ->references('code')
                ->on('roles')
                ->cascadeOnUpdate();
            $table->foreign('department_code', 'fk_users_department')
                ->references('code')
                ->on('departments')
                ->cascadeOnUpdate();
        });

        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 16);
            // Snapshot of the account email used when this OTP was issued.
            $table->string('email', 191);
            // Store only a hash of the OTP; never persist the 6-digit code in plain text.
            $table->string('otp_hash');
            $table->enum('purpose', ['forgot_password'])->default('forgot_password');
            $table->enum('channel', ['email'])->default('email');
            $table->enum('status', ['pending', 'verified', 'used', 'expired', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('last_sent_at')->nullable()->useCurrent();
            $table->string('request_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index('user_id', 'password_reset_otps_user_index');
            $table->index('email', 'password_reset_otps_email_index');
            $table->index('status', 'password_reset_otps_status_index');
            $table->index('expires_at', 'password_reset_otps_expires_at_index');
            $table->index(['user_id', 'status'], 'password_reset_otps_user_status_index');
            $table->foreign('user_id', 'fk_password_reset_otps_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->string('user_id', 16);
            $table->string('permission_code', 64);
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->primary(['user_id', 'permission_code']);
            $table->foreign('user_id', 'fk_user_permissions_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('permission_code', 'fk_user_permissions_permission')
                ->references('code')
                ->on('permissions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type')->default('App\\Models\\User');
            $table->string('tokenable_id', 16);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_index');
            $table->foreign('tokenable_id', 'fk_personal_access_tokens_user')
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
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departments');

        Schema::enableForeignKeyConstraints();
    }
};
