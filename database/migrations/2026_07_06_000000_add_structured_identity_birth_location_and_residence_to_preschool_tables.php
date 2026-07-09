<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preschool_enrollment_applications', function (Blueprint $table): void {
            $table->string('latin_name', 200)->nullable()->after('khmer_name');
            $table->string('ethnicity', 100)->nullable()->after('nationality');
            $table->foreignId('birth_province_id')->nullable()->after('ethnicity')->constrained('cambodia_provinces')->nullOnDelete();
            $table->foreignId('birth_district_id')->nullable()->after('birth_province_id')->constrained('cambodia_districts')->nullOnDelete();
            $table->foreignId('birth_commune_id')->nullable()->after('birth_district_id')->constrained('cambodia_communes')->nullOnDelete();
            $table->foreignId('birth_village_id')->nullable()->after('birth_commune_id')->constrained('cambodia_villages')->nullOnDelete();
            $table->foreignId('residence_province_id')->nullable()->after('birth_village_id')->constrained('cambodia_provinces')->nullOnDelete();
            $table->foreignId('residence_district_id')->nullable()->after('residence_province_id')->constrained('cambodia_districts')->nullOnDelete();
            $table->foreignId('residence_commune_id')->nullable()->after('residence_district_id')->constrained('cambodia_communes')->nullOnDelete();
            $table->foreignId('residence_village_id')->nullable()->after('residence_commune_id')->constrained('cambodia_villages')->nullOnDelete();
        });

        Schema::table('preschool_students', function (Blueprint $table): void {
            $table->string('latin_name', 200)->nullable()->after('last_name');
            $table->string('place_of_birth', 200)->nullable()->after('date_of_birth');
            $table->string('nationality', 100)->nullable()->default('Cambodian')->after('place_of_birth');
            $table->string('ethnicity', 100)->nullable()->after('nationality');
            $table->foreignId('birth_province_id')->nullable()->after('ethnicity')->constrained('cambodia_provinces')->nullOnDelete();
            $table->foreignId('birth_district_id')->nullable()->after('birth_province_id')->constrained('cambodia_districts')->nullOnDelete();
            $table->foreignId('birth_commune_id')->nullable()->after('birth_district_id')->constrained('cambodia_communes')->nullOnDelete();
            $table->foreignId('birth_village_id')->nullable()->after('birth_commune_id')->constrained('cambodia_villages')->nullOnDelete();
            $table->foreignId('residence_province_id')->nullable()->after('birth_village_id')->constrained('cambodia_provinces')->nullOnDelete();
            $table->foreignId('residence_district_id')->nullable()->after('residence_province_id')->constrained('cambodia_districts')->nullOnDelete();
            $table->foreignId('residence_commune_id')->nullable()->after('residence_district_id')->constrained('cambodia_communes')->nullOnDelete();
            $table->foreignId('residence_village_id')->nullable()->after('residence_commune_id')->constrained('cambodia_villages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preschool_students', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('residence_village_id');
            $table->dropConstrainedForeignId('residence_commune_id');
            $table->dropConstrainedForeignId('residence_district_id');
            $table->dropConstrainedForeignId('residence_province_id');
            $table->dropConstrainedForeignId('birth_village_id');
            $table->dropConstrainedForeignId('birth_commune_id');
            $table->dropConstrainedForeignId('birth_district_id');
            $table->dropConstrainedForeignId('birth_province_id');
            $table->dropColumn(['ethnicity', 'nationality', 'place_of_birth', 'latin_name']);
        });

        Schema::table('preschool_enrollment_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('residence_village_id');
            $table->dropConstrainedForeignId('residence_commune_id');
            $table->dropConstrainedForeignId('residence_district_id');
            $table->dropConstrainedForeignId('residence_province_id');
            $table->dropConstrainedForeignId('birth_village_id');
            $table->dropConstrainedForeignId('birth_commune_id');
            $table->dropConstrainedForeignId('birth_district_id');
            $table->dropConstrainedForeignId('birth_province_id');
            $table->dropColumn(['ethnicity', 'latin_name']);
        });
    }
};
