<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    protected array $indexes = [
        'customer_time_series_customer_id_frequency_index'          => ['customer_time_series', 'customer_id'],
        'webpage_time_series_webpage_id_frequency_index'            => ['webpage_time_series', 'webpage_id'],
        'master_asset_time_series_master_asset_id_frequency_index'  => ['master_asset_time_series', 'master_asset_id'],
        'trade_unit_time_series_trade_unit_id_frequency_index'      => ['trade_unit_time_series', 'trade_unit_id'],
        'offer_time_series_offer_id_frequency_index'                => ['offer_time_series', 'offer_id'],
        'master_product_category_time_series_mpc_id_frequency_index' => ['master_product_category_time_series', 'master_product_category_id'],
        'trade_unit_family_time_series_tuf_id_frequency_index'      => ['trade_unit_family_time_series', 'trade_unit_family_id'],
        'outbox_time_series_outbox_id_frequency_index'              => ['outbox_time_series', 'outbox_id'],
        'offer_campaign_time_series_oc_id_frequency_index'          => ['offer_campaign_time_series', 'offer_campaign_id'],
    ];

    public function up(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        foreach ($this->indexes as $name => [$table, $column]) {
            DB::statement("CREATE INDEX {$concurrently}IF NOT EXISTS {$name} ON {$table} ({$column}, frequency)");
        }
    }

    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        foreach (array_keys($this->indexes) as $name) {
            DB::statement("DROP INDEX {$concurrently}IF EXISTS {$name}");
        }
    }
};
