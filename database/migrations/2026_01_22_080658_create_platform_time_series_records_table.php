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
            CREATE TABLE platform_time_series_records (
                id bigserial,
                platform_time_series_id integer NOT NULL,
                organisation_id integer NOT NULL,
                shop_id integer NOT NULL,
                frequency char(1) NOT NULL,
                sales numeric(16,2) DEFAULT 0,
                sales_org_currency numeric(16,2) DEFAULT 0,
                sales_grp_currency numeric(16,2) DEFAULT 0,
                invoices integer DEFAULT 0,
                channels integer DEFAULT 0,
                customers integer DEFAULT 0,
                portfolios integer DEFAULT 0,
                customer_clients integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT platform_time_series_records_platform_time_series_id_foreign
                    FOREIGN KEY (platform_time_series_id)
                    REFERENCES platform_time_series(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT platform_time_series_records_organisation_id_foreign
                    FOREIGN KEY (organisation_id)
                    REFERENCES organisations(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT platform_time_series_records_shop_id_foreign
                    FOREIGN KEY (shop_id)
                    REFERENCES shops(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT platform_time_series_records_unique
                    UNIQUE (platform_time_series_id, shop_id, frequency, period)
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
            $freqPartitionName = "ptsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF platform_time_series_records FOR VALUES IN ('$freqCode')");
        }

        DB::statement('CREATE INDEX ptsr_platform_org_period_idx
            ON platform_time_series_records (platform_time_series_id, organisation_id, period)');

        DB::statement('CREATE INDEX ptsr_platform_shop_period_idx
            ON platform_time_series_records (platform_time_series_id, shop_id, period)');

        DB::statement('CREATE INDEX ptsr_from_to_idx
            ON platform_time_series_records ("from", "to")');

        DB::statement('CREATE INDEX ptsr_period_idx
            ON platform_time_series_records (period)');

        DB::statement('CREATE INDEX ptsr_platform_time_series_id_idx
            ON platform_time_series_records (platform_time_series_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_time_series_records');
    }
};
