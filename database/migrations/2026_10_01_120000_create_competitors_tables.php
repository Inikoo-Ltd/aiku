<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('master_shop_id')->index();
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('website');
            $table->string('sells_to');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->text('feed_url')->nullable();
            $table->text('search_url')->nullable();
            $table->text('login_url')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('cookies')->nullable();
            $table->string('status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('fetched_at')->nullable();
            $table->unsignedInteger('number_products')->default(0);
            $table->timestampsTz();
        });

        Schema::create('competitor_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('competitor_id');
            $table->foreign('competitor_id')->references('id')->on('competitors')->cascadeOnDelete();
            $table->text('code');
            $table->text('url')->nullable();
            $table->text('image_url')->nullable();
            $table->text('name');
            $table->string('barcode')->nullable();
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('rrp', 18, 4)->nullable();
            $table->decimal('units', 12, 3)->default(1);
            $table->unsignedInteger('minimum_order')->nullable();
            $table->timestampTz('fetched_at');
            $table->timestampsTz();
            $table->unique(['competitor_id', 'code']);
            $table->index(['competitor_id', 'barcode']);
        });

        DB::statement('create index competitor_products_name_trgm on competitor_products using gin (name gin_trgm_ops)');

        Schema::create('master_asset_competitor_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('master_shop_id')->index();
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->cascadeOnDelete();
            $table->unsignedInteger('master_asset_id')->index();
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->cascadeOnDelete();
            $table->unsignedInteger('competitor_product_id')->index();
            $table->foreign('competitor_product_id')->references('id')->on('competitor_products')->cascadeOnDelete();
            $table->boolean('is_same_item');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('status')->index();
            $table->unsignedSmallInteger('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['master_asset_id', 'competitor_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_asset_competitor_products');
        Schema::dropIfExists('competitor_products');
        Schema::dropIfExists('competitors');
    }
};
