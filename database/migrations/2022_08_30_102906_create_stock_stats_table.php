<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 18:37:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Stubs\Migrations\HasProcurementStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasProcurementStats;

    public function up(): void
    {
        Schema::create('stock_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('stock_id')->index();
            $table->foreign('stock_id')->references('id')->on('stocks')->onUpdate('cascade')->onDelete('cascade');
            $table->string('quantity_status_from')->nullable()->index();
            $table->string('quantity_status_upto')->nullable()->index();
            $table->unsignedSmallInteger('number_org_stocks')->default(0);
            foreach (OrgStockStateEnum::cases() as $case) {
                $table->unsignedInteger('number_number_org_stocks_state_'.$case->snake())->default(0);
            }
            foreach (OrgStockQuantityStatusEnum::cases() as $stockQuantityStatus) {
                $table->unsignedInteger('number_org_stocks_quantity_status_'.$stockQuantityStatus->snake())->default(0);
            }

            $table = $this->purchaseOrdersStats($table);
            $table = $this->stockDeliveriesStats($table);
            $table = $this->purchaseOrderTransactionsStats($table);
            $table = $this->stockDeliveryItemsStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_stats');
    }
};
