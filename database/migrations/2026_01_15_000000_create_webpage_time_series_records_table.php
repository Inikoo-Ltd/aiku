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
        Schema::dropIfExists('webpage_time_series_records');

        DB::statement('
            CREATE TABLE webpage_time_series_records (
                id bigserial,
                webpage_time_series_id integer NOT NULL,
                frequency char(1) NOT NULL,
                visitors integer DEFAULT 0,
                page_views integer DEFAULT 0,
                add_to_baskets integer DEFAULT 0,
                conversion_rate numeric(5,2) DEFAULT 0,
                avg_time_on_page integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT webpage_time_series_records_id_foreign FOREIGN KEY (webpage_time_series_id) REFERENCES webpage_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $freqPartitionName = "wptsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF webpage_time_series_records FOR VALUES IN ('$freqCode')");
        }

        DB::statement('CREATE INDEX webpage_time_series_records_webpage_time_series_id_index ON webpage_time_series_records (webpage_time_series_id)');
        DB::statement('CREATE INDEX webpage_time_series_records_from_index ON webpage_time_series_records ("from")');
        DB::statement('CREATE INDEX webpage_time_series_records_to_index ON webpage_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('webpage_time_series_records');
    }
};
