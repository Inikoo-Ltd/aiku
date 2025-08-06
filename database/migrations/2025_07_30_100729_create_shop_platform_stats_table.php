<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 06 Aug 2025 13:03:19 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shop_platform_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->unsignedSmallInteger('platform_id');
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedInteger("number_customers")->default(0);
            foreach (CustomerStateEnum::cases() as $customerState) {
                $table->unsignedInteger("number_customers_state_{$customerState->snake()}")->default(0);
            }

            $table->unsignedInteger("number_products")->default(0);
            $table->unsignedInteger("number_current_products")->default(0)->comment('state: active+discontinuing');

            foreach (ProductStateEnum::cases() as $productState) {
                $table->unsignedInteger("number_products_state_{$productState->snake()}")->default(0);
            }

            $table->dateTimeTz("last_order_created_at")->nullable();
            $table->dateTimeTz("last_order_submitted_at")->nullable();
            $table->dateTimeTz("last_order_dispatched_at")->nullable();
            $table->unsignedInteger("number_orders")->default(0);

            foreach (OrderStateEnum::cases() as $case) {
                $table->unsignedInteger('number_orders_state_'.$case->snake())->default(0);
            }

            foreach (OrderStatusEnum::cases() as $case) {
                $table->unsignedInteger('number_orders_status_'.$case->snake())->default(0);
            }

            foreach (OrderHandingTypeEnum::cases() as $case) {
                $table->unsignedInteger('number_orders_handing_type_'.$case->snake())->default(0);
            }
            $table->unsignedInteger('number_customer_sales_channels')->default(0);
            $table->unsignedInteger('number_customer_sales_channel_broken')->default(0);

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shop_platform_stats');
    }
};
