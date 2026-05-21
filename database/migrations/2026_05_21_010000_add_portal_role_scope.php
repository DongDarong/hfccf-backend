<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Guardian portal users need their own scope so the portal role stays
     * separate from staff/admin access and can be revoked independently.
     */
    public function up(): void
    {
        DB::table('role_scopes')->updateOrInsert(
            ['code' => 'portal'],
            ['name' => 'Portal']
        );
    }

    /**
     * Keep the lookup reversible for local development and test databases.
     */
    public function down(): void
    {
        DB::table('role_scopes')
            ->where('code', 'portal')
            ->delete();
    }
};
