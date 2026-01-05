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
        Schema::dropIfExists('website_time_series_records');

        DB::statement('
            CREATE TABLE website_time_series_records (
                id bigserial,
                website_time_series_id integer NOT NULL,
                frequency char(1) NOT NULL,
                visitors integer DEFAULT 0,
                sessions integer DEFAULT 0,
                page_views integer DEFAULT 0,
                avg_session_duration integer DEFAULT 0,
                bounce_rate numeric(5,2) DEFAULT 0,
                pages_per_session numeric(5,2) DEFAULT 0,
                new_visitors integer DEFAULT 0,
                returning_visitors integer DEFAULT 0,
                visitors_desktop integer DEFAULT 0,
                visitors_mobile integer DEFAULT 0,
                visitors_tablet integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT website_time_series_records_id_foreign FOREIGN KEY (website_time_series_id) REFERENCES website_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $freqPartitionName = "wtsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF website_time_series_records FOR VALUES IN ('$freqCode')");
        }

        DB::statement('CREATE INDEX website_time_series_records_website_time_series_id_index ON website_time_series_records (website_time_series_id)');
        DB::statement('CREATE INDEX website_time_series_records_from_index ON website_time_series_records ("from")');
        DB::statement('CREATE INDEX website_time_series_records_to_index ON website_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('website_time_series_records');
    }
};
