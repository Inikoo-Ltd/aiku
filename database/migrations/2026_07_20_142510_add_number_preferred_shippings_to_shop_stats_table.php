<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Mon, 20 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_stats', 'number_preferred_shippings')) {
                $table->unsignedSmallInteger('number_preferred_shippings')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            if (Schema::hasColumn('shop_stats', 'number_preferred_shippings')) {
                $table->dropColumn('number_preferred_shippings');
            }
        });
    }
};
