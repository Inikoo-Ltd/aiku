<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_asset_price_tips', function (Blueprint $table) {
            $table->string('kind')->nullable()->index();
        });

        DB::table('master_asset_price_tips')->where('status', 'open')->update(['status' => 'expired', 'expired_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('master_asset_price_tips', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
