<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('warehouses')
            ->whereRaw("(settings->>'low_stock_threshold') IS NULL")
            ->update([
                'settings' => DB::raw(
                    "jsonb_set(COALESCE(settings, '{}'::jsonb), '{low_stock_threshold}', '".Warehouse::DEFAULT_LOW_STOCK_THRESHOLD."'::jsonb, true)"
                )
            ]);
    }

    public function down(): void
    {
        DB::table('warehouses')
            ->whereRaw("(settings->>'low_stock_threshold') IS NOT NULL")
            ->update([
                'settings' => DB::raw("settings - 'low_stock_threshold'")
            ]);
    }
};
