<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('base_role', 20);
            $table->json('permissions');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('access_role_id')->nullable()->constrained('access_roles')->restrictOnDelete();
        });
        foreach (Role::cases() as $role) {
            $id = DB::table('access_roles')->insertGetId(['code' => $role->value, 'name' => $role->label(), 'base_role' => $role->value,
                'permissions' => json_encode(['dashboard', 'requests', 'calendar', 'approvals', 'reports', 'library']), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('role', $role->value)->update(['access_role_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('access_role_id'));
        Schema::dropIfExists('access_roles');
    }
};
