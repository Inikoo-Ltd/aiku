<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['products', 'master_assets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('customs_trade_unit_id')->nullable();
                $table->foreign('customs_trade_unit_id')->references('id')->on('trade_units')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'master_assets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['customs_trade_unit_id']);
                $table->dropColumn('customs_trade_unit_id');
            });
        }
    }
};
