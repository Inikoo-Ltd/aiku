<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 11:30:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('catalogue_top_listed_families', function (Blueprint $table) {
            $table->smallInteger('shop_id')->nullable();
            $table->unsignedInteger('family_id')->index();
            $table->unsignedInteger('total_listed');
            $table->unsignedInteger('total_customers');
            $table->index(['shop_id', 'total_listed']);
        });

        Schema::create('catalogue_top_listed_products', function (Blueprint $table) {
            $table->smallInteger('shop_id')->nullable();
            $table->unsignedInteger('asset_id')->index();
            $table->unsignedInteger('total_listed');
            $table->unsignedInteger('total_customers');
            $table->index(['shop_id', 'total_listed']);
        });

        Schema::create('catalogue_top_sold_products', function (Blueprint $table) {
            $table->smallInteger('shop_id');
            $table->unsignedInteger('asset_id')->index();
            $table->decimal('total_sold', 24, 6)->nullable();
            $table->decimal('total_amount', 24, 2);
            $table->decimal('total_grp_amount', 24, 2)->nullable();
            $table->index(['shop_id', 'total_sold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_top_sold_products');
        Schema::dropIfExists('catalogue_top_listed_products');
        Schema::dropIfExists('catalogue_top_listed_families');
    }
};
