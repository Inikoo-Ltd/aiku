<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 17:21:46 Central European Summer Time, Plane Vienna - Malaga
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_master_collections')->default(0);
            $table->unsignedSmallInteger('number_current_master_collections')->default(0)->comment('status=true');
        });
    }


    public function down(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn(['number_master_collections', 'number_current_master_collections']);
        });
    }
};
