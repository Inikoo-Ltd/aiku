<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 22:02:57 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasProcurementStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasProcurementStats;

    public function up(): void
    {
        Schema::create('org_agent_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('org_agent_id')->index();
            $table->foreign('org_agent_id')->references('id')->on('org_agents');
            $table = $this->orgSuppliersStats($table);
            $table = $this->orgSupplierProductsStats($table);
            $table = $this->purchaseOrdersStats($table);
            $table = $this->stockDeliveriesStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_agent_stats');
    }
};
