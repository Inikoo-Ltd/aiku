<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 19 May 2026 16:12:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_whatsapp_marketing')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_whatsapp_marketing');
        });
    }
};
