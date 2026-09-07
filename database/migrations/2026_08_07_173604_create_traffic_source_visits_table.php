<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Visits that arrived from a channel, whether or not they ever became a customer.
     *
     * Attribution only sees a visitor once they log in or register, so a channel that sends hundreds
     * of people who all leave is invisible: no touch, no row, nothing to report. That is exactly the
     * case worth knowing about — money spent on clicks that went nowhere.
     *
     * One row per shop, channel and day, folded in from the capture counters by
     * `traffic-source:collect-visits`. Written from a scheduled job rather than the storefront hot
     * path: a database write per page view is not something a marketing count should ever cost.
     */
    public function up(): void
    {
        Schema::create('traffic_source_visits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->unsignedInteger('traffic_source_id');
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources')->onDelete('cascade');
            $table->date('date');
            $table->unsignedInteger('visits')->default(0);
            $table->timestampsTz();

            $table->unique(['traffic_source_id', 'date']);
            $table->index(['shop_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_source_visits');
    }
};
