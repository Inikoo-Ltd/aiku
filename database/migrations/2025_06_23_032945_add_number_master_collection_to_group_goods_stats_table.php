<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 21:00:10 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_master_collections')->default(0);
            $table->unsignedSmallInteger('number_current_master_collections')->default(0)->comment('status=true');
        });
    }


    public function down(): void
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn(['number_master_collections', 'number_current_master_collections']);
        });
    }
};
