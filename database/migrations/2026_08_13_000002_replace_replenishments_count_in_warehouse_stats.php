<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn('number_org_stocks_replenishments');
            $table->unsignedInteger('number_org_stocks_replenishments_wholesale')->default(0);
            $table->unsignedInteger('number_org_stocks_replenishments_dropshipping')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_org_stocks_replenishments_wholesale',
                'number_org_stocks_replenishments_dropshipping',
            ]);
            $table->unsignedInteger('number_org_stocks_replenishments')->default(0);
        });
    }
};
