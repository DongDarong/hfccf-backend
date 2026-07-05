<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('preschool_guardian_communications')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE preschool_guardian_communications MODIFY created_by VARCHAR(16) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('preschool_guardian_communications')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE preschool_guardian_communications MODIFY created_by BIGINT UNSIGNED NULL');
    }
};
