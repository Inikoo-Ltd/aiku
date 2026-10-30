<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_files', function (Blueprint $table) {
            $table->string('model_type')->nullable();
            $table->unsignedInteger('model_id')->nullable();
            $table->index(['model_type', 'model_id']);
        });

        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->string('packaging_sample_status')->nullable();
            $table->text('packaging_sample_note')->nullable();
            $table->unsignedInteger('packaging_sample_reviewed_by_id')->nullable();
            $table->foreign('packaging_sample_reviewed_by_id')->references('id')->on('users');
            $table->timestampTz('packaging_sample_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->dropForeign(['packaging_sample_reviewed_by_id']);
            $table->dropColumn(['packaging_sample_status', 'packaging_sample_note', 'packaging_sample_reviewed_by_id', 'packaging_sample_reviewed_at']);
        });

        Schema::table('product_development_files', function (Blueprint $table) {
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropColumn(['model_type', 'model_id']);
        });
    }
};
