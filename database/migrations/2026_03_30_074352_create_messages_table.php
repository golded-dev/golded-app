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
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('msgno');
            $table->string('external_id')->nullable();
            $table->string('subject');
            $table->string('from_name');
            $table->string('from_address')->nullable();
            $table->string('to_name');
            $table->string('to_address')->nullable();
            $table->longText('body_text');
            $table->unsignedInteger('reply_to_msgno')->nullable();
            $table->string('reply_to_external_id')->nullable();
            $table->unsignedInteger('reply1st_msgno')->nullable();
            $table->unsignedInteger('replynext_msgno')->nullable();
            $table->string('thread_key')->nullable();
            $table->unsignedInteger('attributes_raw')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_marked')->default(false);
            $table->boolean('is_bookmarked')->default(false);
            $table->json('raw_metadata_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
