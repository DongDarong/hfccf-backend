<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_settings_backbone', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_settings_backbone');
    }
};
