<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 11:45:10 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->unsignedInteger('picking_location_id')->nullable()->index();
            $table->foreign('picking_location_id')->references('id')->on('locations')->nullOnDelete();
            $table->unsignedInteger('picking_dropshipping_location_id')->nullable()->index();
            $table->foreign('picking_dropshipping_location_id')->references('id')->on('locations')->nullOnDelete();

        });
    }


    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropForeign(['picking_location_id']);
            $table->dropForeign(['picking_dropshipping_location_id']);

            $table->dropColumn(['picking_location_id', 'picking_dropshipping_location_id']);
        });
    }
};
