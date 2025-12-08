<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 14:50:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasProcurementStats;
use App\Stubs\Migrations\HasSupplyChainStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasProcurementStats;
    use HasSupplyChainStats;

    public function up(): void
    {
        Schema::create('org_partner_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('org_partner_id')->index();
            $table->foreign('org_partner_id')->references('id')->on('org_partners');
            $table = $this->supplierProductsStats($table);
            $table = $this->purchaseOrdersStats($table);
            $table = $this->stockDeliveriesStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_partner_stats');
    }
};
