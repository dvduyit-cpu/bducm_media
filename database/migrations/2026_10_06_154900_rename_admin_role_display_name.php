<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('access_roles')->where('code', 'admin')->update(['name' => 'Admin', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('access_roles')->where('code', 'admin')->update(['name' => 'Admin tổng', 'updated_at' => now()]);
    }
};
