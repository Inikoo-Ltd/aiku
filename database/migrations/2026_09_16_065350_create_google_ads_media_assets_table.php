<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Which Aiku image became which Google Ads asset, per advertising account.
     *
     * Google copies an image into its own library on upload and hands back a resource name; it never
     * matches an image it already holds, so uploading the same product shot for a second campaign
     * would leave two identical assets and two sets of performance figures split between them. This
     * table is what makes the second campaign reuse the first upload.
     *
     * Keyed by shop because the asset belongs to one advertising account: the same image used by two
     * shops is genuinely two uploads.
     */
    public function up(): void
    {
        Schema::create('google_ads_media_assets', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedInteger('media_id');
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();

            $table->string('asset_resource_name');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->timestampsTz();

            $table->unique(['shop_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_media_assets');
    }
};
