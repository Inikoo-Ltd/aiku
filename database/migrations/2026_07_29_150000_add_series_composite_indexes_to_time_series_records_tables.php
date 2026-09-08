<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    protected array $tables = [
        'asset_time_series_records',
        'master_asset_time_series_records',
        'org_stock_time_series_records',
        'trade_unit_time_series_records',
        'customer_time_series_records',
        'offer_time_series_records',
        'org_stock_family_time_series_records',
        'collection_time_series_records',
        'stock_family_time_series_records',
        'trade_unit_family_time_series_records',
        'webpage_time_series_records',
        'invoice_category_time_series_records',
        'outbox_time_series_records',
        'offer_campaign_time_series_records',
        'shop_time_series_records',
        'sales_channel_time_series_records',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $seriesColumn = substr($table, 0, -8).'_id';
            DB::statement("CREATE INDEX IF NOT EXISTS {$table}_series_from_idx ON {$table} ({$seriesColumn}, \"from\")");
            DB::statement("CREATE INDEX IF NOT EXISTS {$table}_series_to_idx ON {$table} ({$seriesColumn}, \"to\")");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_series_from_idx");
            DB::statement("DROP INDEX IF EXISTS {$table}_series_to_idx");
        }
    }
};
