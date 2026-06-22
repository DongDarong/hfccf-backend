<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambodia_provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name_kh', 191);
            $table->string('name_en', 191);
            $table->timestamps();
        });

        Schema::create('cambodia_districts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('cambodia_provinces')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('code', 16)->unique();
            $table->string('name_kh', 191);
            $table->string('name_en', 191);
            $table->timestamps();

            $table->index(['province_id', 'code'], 'cambodia_districts_province_code_index');
        });

        Schema::create('cambodia_communes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('cambodia_provinces')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('district_id')->constrained('cambodia_districts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('code', 16)->unique();
            $table->string('name_kh', 191);
            $table->string('name_en', 191);
            $table->timestamps();

            $table->index(['province_id', 'district_id', 'code'], 'cambodia_communes_parent_code_index');
        });

        Schema::create('cambodia_villages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('cambodia_provinces')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('district_id')->constrained('cambodia_districts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('commune_id')->nullable()->constrained('cambodia_communes')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('code', 16);
            $table->string('name_kh', 191);
            $table->string('name_en', 191);
            $table->timestamps();

            $table->index(['province_id', 'district_id', 'commune_id', 'code'], 'cambodia_villages_parent_code_index');
            $table->index('code', 'cambodia_villages_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambodia_villages');
        Schema::dropIfExists('cambodia_communes');
        Schema::dropIfExists('cambodia_districts');
        Schema::dropIfExists('cambodia_provinces');
    }
};
