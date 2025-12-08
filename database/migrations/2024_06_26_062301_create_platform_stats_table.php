<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 15:04:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Stubs\Migrations\HasCatalogueStats;
use App\Stubs\Migrations\HasOrderingStats;
use App\Stubs\Migrations\HasSalesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCatalogueStats;
    use HasOrderingStats;
    use HasSalesStats;

    public function up(): void
    {
        Schema::create('platform_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('platform_id')->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('number_customers')->default(0);

            foreach (CustomerStateEnum::cases() as $customerState) {
                $table->unsignedInteger("number_customers_state_{$customerState->snake()}")->default(0);
            }

            $table->unsignedInteger('number_products')->default(0);
            $table->unsignedInteger('number_current_products')->default(0)->comment('state: active+discontinuing');

            foreach (ProductStateEnum::cases() as $productState) {
                $table->unsignedInteger('number_products_state_'.$productState->snake())->default(0);
            }

            $table = $this->orderingStatsFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_stats');
    }
};
