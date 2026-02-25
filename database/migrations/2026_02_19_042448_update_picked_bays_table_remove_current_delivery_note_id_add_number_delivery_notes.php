<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Feb 2026 12:24:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('picked_bays', function (Blueprint $table) {
            $table->dropColumn('current_delivery_note_id');
            $table->unsignedSmallInteger('number_delivery_notes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('picked_bays', function (Blueprint $table) {
            $table->unsignedBigInteger('current_delivery_note_id')->after('warehouse_id')->nullable();
            $table->dropColumn('number_delivery_notes');
        });
    }
};
