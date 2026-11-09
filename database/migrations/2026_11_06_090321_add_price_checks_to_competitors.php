<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 17:03:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->boolean('prices_by_hand')->default(false);
        });

        Schema::create('competitor_price_checks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('competitor_id')->index();
            $table->foreign('competitor_id')->references('id')->on('competitors')->cascadeOnDelete();
            $table->unsignedInteger('competitor_product_id')->index();
            $table->foreign('competitor_product_id')->references('id')->on('competitor_products')->cascadeOnDelete();
            $table->unsignedInteger('master_asset_id')->index();
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->cascadeOnDelete();
            $table->decimal('price', 18, 4);
            $table->decimal('units', 12, 3)->default(1);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->decimal('our_unit_price', 18, 4)->nullable();
            $table->decimal('their_unit_price', 18, 4)->nullable();
            $table->smallInteger('difference_pct')->nullable();
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->nullOnDelete();
            $table->unsignedSmallInteger('checked_by_user_id')->nullable();
            $table->foreign('checked_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('checked_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_price_checks');

        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn('prices_by_hand');
        });
    }
};
