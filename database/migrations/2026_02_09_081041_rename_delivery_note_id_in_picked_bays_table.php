<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 16:12:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('picked_bays', function (Blueprint $table) {
            $table->renameColumn('delivery_note_id', 'current_delivery_note_id');
        });
    }


    public function down(): void
    {
        Schema::table('picked_bays', function (Blueprint $table) {
            $table->renameColumn('current_delivery_note_id', 'delivery_note_id');
        });
    }
};
