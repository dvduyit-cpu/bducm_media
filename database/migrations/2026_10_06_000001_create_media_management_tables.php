<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role')->default('staff');
            $table->boolean('active')->default(true);
            $table->string('theme_color', 7)->nullable();
        });
        Schema::create('media_requests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('channel');
            $table->dateTime('event_at');
            $table->dateTime('publish_at')->index();
            $table->dateTime('due_at')->index();
            $table->boolean('important')->default(false);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('product_notes')->nullable();
            $table->string('published_url', 2048)->nullable();
            $table->foreignId('professional_checked_by')->nullable()->constrained('users');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status']);
        });
        Schema::create('request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('path');
            $table->string('kind')->default('source');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'settings', 'attachments', 'request_histories', 'media_requests'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['role', 'active', 'theme_color']);
        });
        Schema::dropIfExists('departments');
    }
};
