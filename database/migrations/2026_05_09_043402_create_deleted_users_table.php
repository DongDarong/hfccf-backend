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
        Schema::create('deleted_users', function (Blueprint $table) {
            $table->id();
            $table->string('original_id', 16)->index();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('username', 191);
            $table->string('email', 191);
            $table->string('phone', 32)->nullable();
            $table->string('role_code', 32);
            $table->string('department_code', 32);
            $table->text('bio')->nullable();
            $table->enum('status', ['active', 'pending', 'inactive', 'suspended']);
            $table->string('avatar', 2048)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('user_created_at')->nullable();
            $table->timestamp('user_updated_at')->nullable();
            $table->timestamp('deleted_at')->useCurrent();
            $table->string('deleted_by', 16)->nullable();
            $table->json('original_data')->nullable(); // Catch-all for extra fields

            $table->index('email', 'deleted_users_email_index');
            $table->index('deleted_at', 'deleted_users_deleted_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_users');
    }
};
