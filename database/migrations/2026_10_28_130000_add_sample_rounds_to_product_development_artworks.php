<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_artwork_versions', function (Blueprint $table) {
            $table->string('sample_status')->nullable();
            $table->text('sample_note')->nullable();
            $table->unsignedInteger('sample_reviewed_by_id')->nullable();
            $table->foreign('sample_reviewed_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('sample_reviewed_at')->nullable();
        });

        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->timestampTz('sample_reopened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->dropColumn('sample_reopened_at');
        });

        Schema::table('product_development_artwork_versions', function (Blueprint $table) {
            $table->dropForeign(['sample_reviewed_by_id']);
            $table->dropColumn(['sample_status', 'sample_note', 'sample_reviewed_by_id', 'sample_reviewed_at']);
        });
    }
};
