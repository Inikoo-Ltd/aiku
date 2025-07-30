<?php

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('shop_has_platforms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->unsignedSmallInteger('platform_id');
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            foreach (PlatformTypeEnum::cases() as $platformType) {
                $table->unsignedInteger("number_customers_platform_type_{$platformType->snake()}")->default(0);
                foreach (CustomerStateEnum::cases() as $customerState) {
                    $table->unsignedInteger("number_customers_platform_type_{$platformType->snake()}_state_{$customerState->snake()}")->default(0);
                }

                $table->unsignedInteger("number_products_platform_type_{$platformType->snake()}")->default(0);
                $table->unsignedInteger("number_current_products_platform_type_{$platformType->snake()}")->default(0)->comment('state: active+discontinuing');

                foreach (ProductStateEnum::cases() as $productState) {
                    $table->unsignedInteger("number_products_platform_type_{$platformType->snake()}_state_{$productState->snake()}")->default(0);
                }

                $table->dateTimeTz("last_order_platform_type_{$platformType->snake()}_created_at")->nullable();
                $table->dateTimeTz("last_order_platform_type_{$platformType->snake()}_submitted_at")->nullable();
                $table->dateTimeTz("last_order_platform_type_{$platformType->snake()}_dispatched_at")->nullable();
                $table->unsignedInteger("number_orders_platform_type_{$platformType->snake()}")->default(0);

                foreach (OrderStateEnum::cases() as $case) {
                    $table->unsignedInteger('number_orders_platform_type_'.$platformType->snake().'_state_'.$case->snake())->default(0);
                }

                foreach (OrderStatusEnum::cases() as $case) {
                    $table->unsignedInteger('number_orders_platform_type_'.$platformType->snake().'_status_'.$case->snake())->default(0);
                }

                foreach (OrderHandingTypeEnum::cases() as $case) {
                    $table->unsignedInteger('number_orders_platform_type_'.$platformType->snake().'_handing_type_'.$case->snake())->default(0);
                }
            }
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shop_has_platforms');
    }
};
