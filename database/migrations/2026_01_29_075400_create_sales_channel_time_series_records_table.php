<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE sales_channel_time_series_records (
                id bigserial,
                sales_channel_time_series_id integer NOT NULL,
                frequency char(1) NOT NULL,
                sales numeric(16,2) DEFAULT 0,
                sales_org_currency numeric(16,2) DEFAULT 0,
                sales_grp_currency numeric(16,2) DEFAULT 0,
                lost_revenue numeric(16,2) DEFAULT 0,
                lost_revenue_org_currency numeric(16,2) DEFAULT 0,
                lost_revenue_grp_currency numeric(16,2) DEFAULT 0,
                invoices integer DEFAULT 0,
                refunds integer DEFAULT 0,
                customers_invoiced integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT sales_channel_time_series_records_id_foreign FOREIGN KEY (sales_channel_time_series_id) REFERENCES sales_channel_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $freqPartitionName = "sctsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF sales_channel_time_series_records FOR VALUES IN ('$freqCode')");
        }

        DB::statement('CREATE INDEX sales_channel_time_series_records_sales_channel_time_series_id_index ON sales_channel_time_series_records (sales_channel_time_series_id)');
        DB::statement('CREATE INDEX sales_channel_time_series_records_from_index ON sales_channel_time_series_records ("from")');
        DB::statement('CREATE INDEX sales_channel_time_series_records_to_index ON sales_channel_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_channel_time_series_records');
    }
};
