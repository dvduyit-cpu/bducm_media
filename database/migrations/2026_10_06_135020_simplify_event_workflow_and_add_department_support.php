<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_requests', function (Blueprint $table) {
            $table->string('coordination_mode')->default('pending');
            $table->text('support_notes')->nullable();
            $table->string('task_color', 7)->default('#2563eb');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
        });
        Schema::table('users', fn (Blueprint $table) => $table->json('board_preferences')->nullable());
        Schema::create('department_media_request', function (Blueprint $table) {
            $table->foreignId('media_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->primary(['media_request_id', 'department_id']);
            $table->timestamps();
        });
        $mapping = ['on_hold' => 'submitted', 'internal_review' => 'submitted', 'received' => 'submitted', 'ready' => 'submitted', 'awaiting_assignment' => 'submitted', 'assigned' => 'approved', 'pending_approval' => 'in_progress', 'revision' => 'in_progress', 'published' => 'in_progress'];
        foreach ($mapping as $before => $after) {
            DB::table('media_requests')->where('status', $before)->update(['status' => $after]);
        }
        DB::table('media_requests')->whereNotIn('status', ['completed', 'cancelled'])->whereColumn('due_at', '<', 'event_at')->update(['due_at' => DB::raw('COALESCE(event_ends_at, event_at)')]);
        DB::table('media_requests')->whereIn('status', ['approved', 'in_progress', 'completed'])->update(['coordination_mode' => 'autonomous']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_media_request');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('board_preferences'));
        Schema::table('media_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['coordination_mode', 'support_notes', 'task_color', 'approved_at']);
        });
    }
};
