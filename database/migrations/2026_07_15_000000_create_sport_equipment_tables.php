<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_equipment_items', function (Blueprint $table): void {
            $table->id();
            $table->string('equipment_code', 32)->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->string('unit', 32);
            $table->unsignedInteger('total_quantity')->default(0);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('minimum_stock_level')->default(0)->index();
            $table->string('storage_location')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->string('created_by_user_id', 32)->nullable()->index();
            $table->string('updated_by_user_id', 32)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('sport_equipment_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_code', 32)->unique();
            $table->foreignId('equipment_item_id')->constrained('sport_equipment_items')->restrictOnDelete();
            $table->string('coach_user_id', 32)->index();
            $table->foreignId('team_id')->constrained('sport_teams')->restrictOnDelete();
            $table->unsignedInteger('requested_quantity');
            $table->unsignedInteger('approved_quantity')->nullable();
            $table->unsignedInteger('issued_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('missing_quantity')->default(0);
            $table->text('purpose');
            $table->date('required_date')->index();
            $table->date('expected_return_date')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->string('reviewed_by_user_id', 32)->nullable()->index();
            $table->dateTime('reviewed_at')->nullable()->index();
            $table->string('issued_by_user_id', 32)->nullable()->index();
            $table->dateTime('issued_at')->nullable()->index();
            $table->string('returned_by_user_id', 32)->nullable()->index();
            $table->dateTime('returned_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('coach_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('issued_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('returned_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_equipment_requests');
        Schema::dropIfExists('sport_equipment_items');
    }
};
