<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_requests', function (Blueprint $table) {
            $table->string('record_type')->default('communication');
            $table->dateTime('event_ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('activity_type')->nullable();
            $table->text('communication_angle')->nullable();
            $table->boolean('needs_onsite')->default(false);
            $table->boolean('provides_materials')->default(false);
            $table->string('coverage_status')->default('pending');
            $table->string('approval_route')->default('auto');
            $table->json('content_tags')->nullable();
            $table->foreignId('media_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('publish_at')->nullable()->change();
            $table->dateTime('due_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_assignee_id');
            $table->dropColumn(['record_type', 'event_ends_at', 'location', 'activity_type', 'communication_angle', 'needs_onsite', 'provides_materials', 'coverage_status', 'approval_route', 'content_tags', 'published_at']);
        });
    }
};
