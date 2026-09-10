<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            /* Marks stock promised to the partner and waiting to be walked to their goods out
               location. Pre-picking reserves stock, it does not sell anything. */
            $table->dateTimeTz('pre_picked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('pre_picked_at');
        });
    }
};
