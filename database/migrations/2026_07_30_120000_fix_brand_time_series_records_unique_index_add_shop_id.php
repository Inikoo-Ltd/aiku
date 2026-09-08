<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS brand_time_series_records_nk_unique');
        DB::statement('DELETE FROM brand_time_series_records a USING brand_time_series_records b WHERE a.brand_time_series_id = b.brand_time_series_id AND a.shop_id = b.shop_id AND a.period = b.period AND a.frequency = b.frequency AND a.id < b.id');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS brand_time_series_records_nk_unique ON brand_time_series_records (brand_time_series_id, shop_id, period, frequency)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS brand_time_series_records_nk_unique');
    }
};
