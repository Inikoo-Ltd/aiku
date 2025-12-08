<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 13:17:59 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Stubs\Migrations\HasProcurementStats;
use App\Stubs\Migrations\IsProcurementOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasProcurementStats;
    use IsProcurementOrder;

    public function up(): void
    {
        Schema::create('stock_deliveries', function (Blueprint $table) {
            $table = $this->headProcurementOrder($table);

            $table->string('state')->index()->default(StockDeliveryStateEnum::IN_PROCESS->value);
            $table->dateTimeTz('date')->comment('latest relevant date');

            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('checked_at')->nullable();
            $table->dateTimeTz('placed_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->dateTimeTz('not_received_at')->nullable();

            $table = $this->bodyProcurementOrder($table);

            $table->unsignedSmallInteger('number_purchase_orders')->default(0)->index();

            $table = $this->statsProcurementOrder($table);
            $table = $this->stockDeliveryItemsStats($table);
            $table = $this->costingProcurementOrder($table);

            return $this->footerProcurementOrder($table);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_deliveries');
    }
};
