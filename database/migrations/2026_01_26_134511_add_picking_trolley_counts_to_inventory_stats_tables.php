<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jan 2026 13:45:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_inventory_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_current_picking_trolleys')->default(0)->comment('Total picking trolleys status=true');
            $table->unsignedInteger('number_current_picking_trolleys_in_use')->default(0)->comment('Total picking trolleys in use status=true, delivery_note_id not null');
            $table->unsignedInteger('number_picking_trolleys')->default(0)->comment('Total picking trolleys including status=false ones');

            $table->unsignedInteger('number_current_picked_bays')->default(0)->comment('Total picking trolleys status=true');
            $table->unsignedInteger('number_current_picked_bays_in_use')->default(0)->comment('Total picking trolleys in use status=true, delivery_note_id not null');
            $table->unsignedInteger('number_picked_bays')->default(0)->comment('Total picking trolleys including status=false ones');


        });

        Schema::table('organisation_inventory_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_current_picking_trolleys')->default(0)->comment('Total picking trolleys status=true');
            $table->unsignedInteger('number_current_picking_trolleys_in_use')->default(0)->comment('Total picking trolleys in use status=true, delivery_note_id not null');
            $table->unsignedInteger('number_picking_trolleys')->default(0)->comment('Total picking trolleys including status=false ones');

            $table->unsignedInteger('number_current_picked_bays')->default(0)->comment('Total picking trolleys status=true');
            $table->unsignedInteger('number_current_picked_bays_in_use')->default(0)->comment('Total picking trolleys in use status=true, delivery_note_id not null');
            $table->unsignedInteger('number_picked_bays')->default(0)->comment('Total picking trolleys including status=false ones');

        });
    }

    public function down(): void
    {
        Schema::table('group_inventory_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_current_picking_trolleys',
                'number_current_picking_trolleys_in_use',
                'number_picking_trolleys',
                'number_current_picked_bays',
                'number_current_picked_bays_in_use',
                'number_picked_bays',
            ]);
        });

        Schema::table('organisation_inventory_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_current_picking_trolleys',
                'number_current_picking_trolleys_in_use',
                'number_picking_trolleys',
                'number_current_picked_bays',
                'number_current_picked_bays_in_use',
                'number_picked_bays',
            ]);
        });
    }
};
