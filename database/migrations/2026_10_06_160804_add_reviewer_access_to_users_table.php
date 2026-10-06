<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('reviewer_access')->default(false));
        foreach (DB::table('users')->where('role', 'office')->where('view_all_units', true)->get() as $user) {
            $permissions = json_decode($user->permissions ?? '[]', true) ?? [];
            $rolePermissions = json_decode(DB::table('access_roles')->where('id', $user->access_role_id)->value('permissions') ?? '[]', true) ?? [];
            if (in_array('requests', $permissions, true) && in_array('approvals', $permissions, true) && in_array('approvals', $rolePermissions, true)) {
                DB::table('users')->where('id', $user->id)->update(['reviewer_access' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('reviewer_access'));
    }
};
