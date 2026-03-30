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
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('echoid')->nullable();
            $table->char('group_id', 1)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('message_count')->nullable();
            $table->unsignedInteger('unread_count')->nullable();
            $table->unsignedInteger('last_read_msgno')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
