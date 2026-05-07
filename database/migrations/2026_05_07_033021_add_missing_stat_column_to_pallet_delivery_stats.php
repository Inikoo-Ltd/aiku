<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 16:42:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('pallet_delivery_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('pallet_delivery_stats', 'number_pallets_state_not_picked')) {
                $table->integer('number_pallets_state_not_picked')->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('pallet_delivery_stats', function (Blueprint $table) {
            if (Schema::hasColumn('pallet_delivery_stats', 'number_pallets_state_not_picked')) {
                $table->dropColumn([
                    'number_pallets_state_not_picked'
                ]);
            }
        });
    }
};
