<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 13:48:51 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('type')->index()->default('image');
            $table->string('model_type')->nullable();
            $table->unsignedInteger('model_id')->nullable();

            $table->string('collection_name')->index();
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();

            $table->uuid()->nullable()->unique();
            $table->ulid()->index();
            $table->string('checksum')->index()->nullable();

            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');

            $table->unsignedSmallInteger('multiplicity')->index()->default(1);
            $table->unsignedSmallInteger('usage')->index()->default(1);
            $table->boolean('is_animated')->default(false);
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
            $table->index(['model_type', 'model_id']);

        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->foreign('image_id')->references('id')->on('media');
        });

    }

    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('image_id_foreign');
        });
        Schema::dropIfExists('media');
    }
};
