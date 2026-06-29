<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_class_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_kh', 100)->nullable();
            $table->string('code', 10)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'preschool_class_levels_active_sort_index');
        });

        Schema::table('preschool_classes', function (Blueprint $table): void {
            $table->foreignId('class_level_id')
                ->nullable()
                ->after('teacher_display_name')
                ->constrained('preschool_class_levels')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('class_level_id', 'preschool_classes_class_level_id_index');
        });

        $now = now();
        DB::table('preschool_class_levels')->insert([
            [
                'name_en' => 'Nursery',
                'name_kh' => 'មត្តេយ្យកម្រិតតូច',
                'code' => 'NUR',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Kindergarten A',
                'name_kh' => 'មត្តេយ្យ A',
                'code' => 'KGA',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Kindergarten B',
                'name_kh' => 'មត្តេយ្យ B',
                'code' => 'KGB',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_en' => 'Prep',
                'name_kh' => 'ត្រៀមចូលរៀន',
                'code' => 'PRE',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $levels = DB::table('preschool_class_levels')->pluck('id', 'code');
        $levelMap = [
            'nursery' => $levels['NUR'] ?? null,
            'kindergarten a' => $levels['KGA'] ?? null,
            'kindergarten b' => $levels['KGB'] ?? null,
            'prep' => $levels['PRE'] ?? null,
        ];

        foreach ($levelMap as $legacyLevel => $classLevelId) {
            if (! $classLevelId) {
                continue;
            }

            DB::table('preschool_classes')
                ->whereRaw('LOWER(`level`) = ?', [$legacyLevel])
                ->update([
                    'class_level_id' => $classLevelId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('preschool_classes', 'class_level_id')) {
            Schema::table('preschool_classes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('class_level_id');
            });
        }

        Schema::dropIfExists('preschool_class_levels');
    }
};
