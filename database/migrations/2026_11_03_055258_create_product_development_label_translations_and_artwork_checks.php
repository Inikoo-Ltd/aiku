<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_development_label_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_development_item_id');
            $table->foreign('product_development_item_id', 'pd_label_translations_item_foreign')->references('id')->on('product_development_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('languages');
            $table->text('text');
            $table->string('source_hash', 64);
            $table->unsignedInteger('translated_by_id')->nullable();
            $table->foreign('translated_by_id')->references('id')->on('users');
            $table->timestampsTz();
            $table->unique(['product_development_item_id', 'language_id'], 'pd_label_translations_item_language_unique');
        });

        Schema::create('product_development_artwork_checks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('artwork_id')->index();
            $table->foreign('artwork_id')->references('id')->on('product_development_artworks')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('state')->default('checking');
            $table->jsonb('result')->nullable();
            $table->string('error')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('requested_by_id')->nullable();
            $table->foreign('requested_by_id')->references('id')->on('users');
            $table->timestampTz('checked_at')->nullable();
            $table->timestampsTz();
            $table->unique(['artwork_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_development_artwork_checks');
        Schema::dropIfExists('product_development_label_translations');
    }
};
