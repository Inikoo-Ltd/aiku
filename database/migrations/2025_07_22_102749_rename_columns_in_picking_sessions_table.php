<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 11:31:21 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->renameColumn('number_picking_session_items', 'number_items');
            $table->renameColumn('number_picking_session_items_picked', 'number_items_picked');
        });
    }


    public function down(): void
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->renameColumn('number_items', 'number_picking_session_items');
            $table->renameColumn('number_items_picked', 'number_picking_session_items_picked');
        });
    }
};
