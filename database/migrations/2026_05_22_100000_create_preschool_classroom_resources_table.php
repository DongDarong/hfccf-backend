<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_classroom_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->enum('category', ['books', 'toys', 'equipment', 'supplies', 'digital'])->default('supplies');
            $table->unsignedInteger('quantity')->default(0);
            $table->enum('condition', ['good', 'fair', 'poor'])->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'condition'], 'classroom_resources_category_condition_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_classroom_resources');
    }
};
