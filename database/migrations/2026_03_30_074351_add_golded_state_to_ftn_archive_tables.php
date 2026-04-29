<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table): void {
            $table->unsignedInteger('message_count')->nullable();
            $table->unsignedInteger('unread_count')->nullable();
            $table->unsignedInteger('last_read_msgno')->nullable();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->boolean('is_read')->default(false);
            $table->boolean('is_marked')->default(false);
            $table->boolean('is_bookmarked')->default(false);
            $table->string('thread_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn([
                'is_read',
                'is_marked',
                'is_bookmarked',
                'thread_key',
            ]);
        });

        Schema::table('areas', function (Blueprint $table): void {
            $table->dropColumn([
                'message_count',
                'unread_count',
                'last_read_msgno',
            ]);
        });
    }
};
