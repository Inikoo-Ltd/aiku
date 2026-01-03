<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 01:54:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('asset_time_series_records');

        DB::statement('
            CREATE TABLE asset_time_series_records (
                id bigserial,
                asset_time_series_id integer NOT NULL,
                frequency char(1) NOT NULL,
                sales numeric(16,2) DEFAULT 0,
                sales_org_currency numeric(16,2) DEFAULT 0,
                sales_grp_currency numeric(16,2) DEFAULT 0,
                invoices integer DEFAULT 0,
                refunds integer DEFAULT 0,
                orders integer DEFAULT 0,
                delivery_notes integer DEFAULT 0,
                customers_invoiced integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT asset_time_series_records_asset_time_series_id_foreign FOREIGN KEY (asset_time_series_id) REFERENCES asset_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) PARTITION BY LIST (frequency);
        ');

        $frequencies = [
            'D' => 'daily',
            'W' => 'weekly',
            'M' => 'monthly',
            'Q' => 'quarterly',
            'Y' => 'yearly',
        ];

        foreach ($frequencies as $freqCode => $freqLabel) {
            $freqPartitionName = "atsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF asset_time_series_records FOR VALUES IN ('$freqCode')");

        }

        DB::statement('CREATE INDEX asset_time_series_records_asset_time_series_id_index ON asset_time_series_records (asset_time_series_id)');
        DB::statement('CREATE INDEX asset_time_series_records_from_index ON asset_time_series_records ("from")');
        DB::statement('CREATE INDEX asset_time_series_records_to_index ON asset_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_time_series_records');
    }
};
