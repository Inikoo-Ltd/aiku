<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 01:56:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('asset_time_series', function (Blueprint $table) {
            $table->unsignedInteger('shop_id')->nullable()->after('asset_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('asset_time_series', function (Blueprint $table) {
            $table->dropColumn('shop_id');
        });
    }
};
