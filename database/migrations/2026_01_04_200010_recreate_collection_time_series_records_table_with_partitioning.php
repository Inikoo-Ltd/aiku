<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Jan 2026 23:30:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('collection_time_series_records');

        DB::statement('
            CREATE TABLE collection_time_series_records (
                id bigserial,
                collection_time_series_id integer NOT NULL,
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
                CONSTRAINT collection_time_series_records_id_foreign FOREIGN KEY (collection_time_series_id) REFERENCES collection_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $freqPartitionName = "ctsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF collection_time_series_records FOR VALUES IN ('$freqCode')");

        }

        DB::statement('CREATE INDEX collection_time_series_records_collection_time_series_id_index ON collection_time_series_records (collection_time_series_id)');
        DB::statement('CREATE INDEX collection_time_series_records_from_index ON collection_time_series_records ("from")');
        DB::statement('CREATE INDEX collection_time_series_records_to_index ON collection_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_time_series_records');
    }
};
