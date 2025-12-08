<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 22:58:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picking_route_has_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('picking_route_id')->index();
            $table->foreign('picking_route_id')->references('id')->on('picking_routes');
            $table->unsignedInteger('location_id')->index();
            $table->foreign('location_id')->references('id')->on('locations');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_route_has_locations');
    }
};
