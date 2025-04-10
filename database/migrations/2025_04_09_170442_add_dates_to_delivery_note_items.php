<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 01:06:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dateTimeTz('date')->nullable()->index();
            $table->dateTimeTz('queued_at')->nullable();
            $table->dateTimeTz('handling_at')->nullable();
            $table->dateTimeTz('handling_blocked_at')->nullable();
            $table->dateTimeTz('packed_at')->nullable();
            $table->dateTimeTz('finalised_at')->nullable();
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            $table->dateTimeTz('start_picking')->nullable();
            $table->dateTimeTz('end_picking')->nullable();
            $table->dateTimeTz('start_packing')->nullable();
            $table->dateTimeTz('end_packing')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn('date');
            $table->dropColumn('queued_at');
            $table->dropColumn('handling_at');
            $table->dropColumn('handling_blocked_at');
            $table->dropColumn('packed_at');
            $table->dropColumn('finalised_at');
            $table->dropColumn('dispatched_at');
            $table->dropColumn('cancelled_at');

            $table->dropColumn('start_picking');
            $table->dropColumn('end_picking');
            $table->dropColumn('start_packing');
            $table->dropColumn('end_packing');

        });
    }
};
