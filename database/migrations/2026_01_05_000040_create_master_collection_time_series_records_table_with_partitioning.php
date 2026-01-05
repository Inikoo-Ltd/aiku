<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 03:09:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('master_collection_time_series_records');

        DB::statement('
            CREATE TABLE master_collection_time_series_records (
                id bigserial,
                master_collection_time_series_id integer NOT NULL,
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
                CONSTRAINT master_collection_time_series_records_id_foreign FOREIGN KEY (master_collection_time_series_id) REFERENCES master_collection_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $freqPartitionName = "mctsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF master_collection_time_series_records FOR VALUES IN ('$freqCode')");

        }

        DB::statement('CREATE INDEX mctsr_master_collection_time_series_id_index ON master_collection_time_series_records (master_collection_time_series_id)');
        DB::statement('CREATE INDEX mctsr_from_index ON master_collection_time_series_records ("from")');
        DB::statement('CREATE INDEX mctsr_to_index ON master_collection_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('master_collection_time_series_records');
    }
};
