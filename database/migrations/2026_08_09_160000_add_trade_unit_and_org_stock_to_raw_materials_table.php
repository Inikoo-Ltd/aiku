<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 16:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->unsignedInteger('trade_unit_id')->nullable()->index();
            $table->foreign('trade_unit_id')->references('id')->on('trade_units')->nullOnDelete();
            $table->unsignedInteger('org_stock_id')->nullable()->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropForeign(['trade_unit_id']);
            $table->dropForeign(['org_stock_id']);
            $table->dropColumn(['trade_unit_id', 'org_stock_id']);
        });
    }
};
