<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 16:40:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items')) {
                $table->integer('number_pallet_returns_items')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items_state_in_process')) {
                $table->integer('number_pallet_returns_items_state_in_process')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items_state_submitted')) {
                $table->integer('number_pallet_returns_items_state_submitted')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items_state_confirmed')) {
                $table->integer('number_pallet_returns_items_state_confirmed')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items_state_picking')) {
                $table->integer('number_pallet_returns_items_state_picking')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items_state_picked')) {
                $table->integer('number_pallet_returns_items_state_picked')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items_state_dispatched')) {
                $table->integer('number_pallet_returns_items_state_dispatched')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_items_state_cancel')) {
                $table->integer('number_pallet_returns_items_state_cancel')->default(0);
            }

            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet')) {
                $table->integer('number_pallet_returns_pallet')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet_state_in_process')) {
                $table->integer('number_pallet_returns_pallet_state_in_process')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet_state_submitted')) {
                $table->integer('number_pallet_returns_pallet_state_submitted')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet_state_confirmed')) {
                $table->integer('number_pallet_returns_pallet_state_confirmed')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet_state_picking')) {
                $table->integer('number_pallet_returns_pallet_state_picking')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet_state_picked')) {
                $table->integer('number_pallet_returns_pallet_state_picked')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet_state_dispatched')) {
                $table->integer('number_pallet_returns_pallet_state_dispatched')->default(0);
            }
            if (!Schema::hasColumn('fulfilment_stats', 'number_pallet_returns_pallet_state_cancel')) {
                $table->integer('number_pallet_returns_pallet_state_cancel')->default(0);
            }

            if (!Schema::hasColumn('fulfilment_stats', 'number_pallets_state_not_picked')) {
                $table->integer('number_pallets_state_not_picked')->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_pallet_returns_items',
                'number_pallet_returns_items_state_in_process',
                'number_pallet_returns_items_state_submitted',
                'number_pallet_returns_items_state_confirmed',
                'number_pallet_returns_items_state_picking',
                'number_pallet_returns_items_state_picked',
                'number_pallet_returns_items_state_dispatched',
                'number_pallet_returns_items_state_cancel',

                'number_pallet_returns_pallet',
                'number_pallet_returns_pallet_state_in_process',
                'number_pallet_returns_pallet_state_submitted',
                'number_pallet_returns_pallet_state_confirmed',
                'number_pallet_returns_pallet_state_picking',
                'number_pallet_returns_pallet_state_picked',
                'number_pallet_returns_pallet_state_dispatched',
                'number_pallet_returns_pallet_state_cancel',

                'number_pallets_state_not_picked',
            ]);
        });
    }
};
