<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 13 Oct 2026 11:31:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('mailshot_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_purchases')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('mailshot_stats', function (Blueprint $table) {
            $table->dropColumn('number_purchases');
        });
    }
};
