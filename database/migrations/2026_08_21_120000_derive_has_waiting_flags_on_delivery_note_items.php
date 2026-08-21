<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Aug 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * has_waiting_* were plain booleans hand-maintained by a dozen write paths, each recomputing
 * "quantity > 0" locally and each able to forget. Whenever one did, a delivery note stayed blocked
 * against a line that no longer waited for anything. Deriving them in the database makes that
 * impossible: the flag is the quantity, nothing else.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn(['has_waiting_warehouse', 'has_waiting_crm']);
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->boolean('has_waiting_warehouse')->storedAs('quantity_waiting_warehouse > 0');
            $table->boolean('has_waiting_crm')->storedAs('quantity_waiting_crm > 0');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn(['has_waiting_warehouse', 'has_waiting_crm']);
        });

        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->boolean('has_waiting_warehouse')->default(false);
            $table->boolean('has_waiting_crm')->default(false);
        });

        DB::statement('update delivery_note_items set has_waiting_warehouse = quantity_waiting_warehouse > 0, has_waiting_crm = quantity_waiting_crm > 0');
    }
};
