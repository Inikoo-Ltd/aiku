<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 13:18:44 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
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
        Schema::create('purchase_orders', function (Blueprint $table) {

            $table = $this->headProcurementOrder($table);
            $table->string('state')->index()->default(PurchaseOrderTransactionStateEnum::IN_PROCESS->value);
            $table->string('delivery_state')->index()->default(PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value);
            $table->text('notes')->nullable();
            $table->dateTimeTz('date')->comment('latest relevant date');
            $table->dateTimeTz('in_process_at')->nullable();
            $table->dateTimeTz('submitted_at')->nullable();
            $table->dateTimeTz('confirmed_at')->nullable();
            $table->dateTimeTz('settled_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->dateTimeTz('not_received_at')->nullable();

            $table = $this->bodyProcurementOrder($table);
            $table = $this->statsProcurementOrder($table);
            $table = $this->purchaseOrderTransactionsStats($table);

            $table = $this->costingProcurementOrder($table);
            $table = $this->stockDeliveriesStats($table);

            return $this->footerProcurementOrder($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
