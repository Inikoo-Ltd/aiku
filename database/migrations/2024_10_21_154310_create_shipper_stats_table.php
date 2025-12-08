<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Oct 2024 23:45:49 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDispatchingStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasDispatchingStats;

    public function up(): void
    {
        Schema::create('shipper_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('shipper_id')->index();
            $table->foreign('shipper_id')->references('id')->on('shippers');
            $table->timestampTz('first_used_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->unsignedInteger('number_customers')->default(0);
            $table->unsignedInteger('number_delivery_notes')->default(0);
            $table = $this->shipmentsStatsFields($table);
            $table = $this->shipmentTrackingsStatsFields($table);
            $table = $this->shipperAccountsStatsFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipper_stats');
    }
};
