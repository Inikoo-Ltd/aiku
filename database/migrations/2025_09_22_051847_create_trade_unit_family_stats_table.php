<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Sept 2025 14:18:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('trade_unit_family_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('trade_unit_family_id')->index();
            $table->foreign('trade_unit_family_id')->references('id')->on('trade_unit_families');
            $table->unsignedInteger('number_trade_units')->default(0);
            $table->timestampsTz();
        });

        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_trade_unit_families')->default(0);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('trade_unit_family_stats');

        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn('number_trade_unit_families');
        });
    }
};
