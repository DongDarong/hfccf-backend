<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The guardian portal gets its own normalized account table so portal
     * access can be revoked independently from guardian relationship data.
     */
    public function up(): void
    {
        // This migration may be re-run after a partial failure on an existing
        // install, so keep it idempotent instead of dropping portal data.
        if (Schema::hasTable('preschool_guardian_portal_accounts')) {
            return;
        }

        Schema::create('preschool_guardian_portal_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guardian_id')->constrained('preschool_guardians')->cascadeOnDelete();
            // Users in HFCCF use short string primary keys, so the portal
            // account must match the existing auth schema instead of assuming
            // an auto-increment bigint foreign key.
            $table->string('user_id', 16)->nullable()->unique();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('email')->unique();
            $table->string('status', 20)->index();
            $table->string('invited_by_user_id', 16)->nullable();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('guardian_id');
            $table->index('user_id');
            // Keep the status index explicitly named so repeated sqlite test
            // migrations do not collide with the default generated index name.
            $table->index('status', 'preschool_guardian_portal_accounts_status_idx');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_guardian_portal_accounts');
    }
};
