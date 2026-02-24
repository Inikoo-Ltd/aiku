<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Feb 2026 15:41:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('follow_trade_unit_media')->default(true)->index();
        });
    }


    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('follow_trade_unit_media');
        });
    }
};
