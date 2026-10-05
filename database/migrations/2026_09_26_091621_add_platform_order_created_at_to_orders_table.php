<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 10:16:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'platform_order_created_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestampTz('platform_order_created_at')->nullable();
            });
        }

        DB::statement("UPDATE orders SET platform_order_created_at = (data->'ebay_order'->>'creationDate')::timestamptz WHERE platform_id IN (SELECT id FROM platforms WHERE type = 'ebay') AND platform_order_created_at IS NULL AND data->'ebay_order'->>'creationDate' IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('platform_order_created_at');
        });
    }
};
