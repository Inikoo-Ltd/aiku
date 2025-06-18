<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:03:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('trade_unit_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('trade_unit_id')->index();
            $table->foreign('trade_unit_id')->references('id')->on('trade_units')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('number_products')->default(0)->comment('Number of products in this trade unit');
            $table->unsignedInteger('number_current_products')->default(0)->comment('Number of products in this trade unit that are active or discontinuing');
            foreach (ProductStateEnum::cases() as $case) {
                $table->unsignedInteger('number_products_state_'.$case->snake())->default(0);
            }
            $table->unsignedInteger('number_customer_exclusive_products')->default(0)->comment('Number of products in this trade unit');
            $table->unsignedInteger('number_customer_exclusive_current_products')->default(0)->comment('Number of products in this trade unit that are active or discontinuing');
            foreach (ProductStateEnum::cases() as $case) {
                $table->unsignedInteger('number_customer_exclusive_products_state_'.$case->snake())->default(0);
            }

            $table->unsignedInteger('number_org_stocks')->default(0);
            $table->unsignedInteger('number_current_org_stocks')->default(0)->comment('Number of org stocks in this trade unit that are active or discontinuing');
            foreach (OrgStockStateEnum::cases() as $case) {
                $table->unsignedInteger('number_org_stocks_state_'.$case->snake())->default(0);
            }

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('trade_unit_stats');
    }
};
