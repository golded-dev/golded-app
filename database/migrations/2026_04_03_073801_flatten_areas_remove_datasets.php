<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add source_type to areas before we lose the datasets FK
        Schema::table('areas', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('dataset_id');
        });

        // Copy source_type from datasets → areas
        DB::statement('UPDATE areas SET source_type = (SELECT source_type FROM datasets WHERE datasets.id = areas.dataset_id)');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['dataset_id']);
            $table->dropColumn('dataset_id');
        });

        Schema::table('drafts', function (Blueprint $table) {
            $table->dropForeign(['dataset_id']);
            $table->dropColumn('dataset_id');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropForeign(['dataset_id']);
            $table->dropColumn('dataset_id');
        });

        Schema::dropIfExists('datasets');
    }

    public function down(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_type');
            $table->timestamps();
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->foreignId('dataset_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('dataset_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('drafts', function (Blueprint $table) {
            $table->foreignId('dataset_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
