<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_attendance_sessions', function (Blueprint $table): void {
            $table->string('opened_by', 50)->nullable()->after('created_by');
            $table->timestamp('opened_at')->nullable()->after('opened_by');
            $table->string('completed_by', 50)->nullable()->after('opened_at');
            $table->timestamp('completed_at')->nullable()->after('completed_by');
            $table->string('locked_by', 50)->nullable()->after('completed_at');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->string('reopened_by', 50)->nullable()->after('locked_at');
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->string('cancelled_by', 50)->nullable()->after('reopened_at');
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('preschool_attendance_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'opened_by',
                'opened_at',
                'completed_by',
                'completed_at',
                'locked_by',
                'locked_at',
                'reopened_by',
                'reopened_at',
                'cancelled_by',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
