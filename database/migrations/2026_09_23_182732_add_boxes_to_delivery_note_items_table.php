<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->jsonb('boxes')->nullable()->comment('[{box, quantity}] parcel numbers the picked quantity was packed in, for box packing lists');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn('boxes');
        });
    }
};
