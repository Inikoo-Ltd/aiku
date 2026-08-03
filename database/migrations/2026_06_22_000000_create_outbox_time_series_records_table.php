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
            CREATE TABLE outbox_time_series_records (
                id bigserial,
                outbox_time_series_id smallint NOT NULL,
                frequency char(1) NOT NULL,
                runs integer DEFAULT 0,
                dispatched_emails integer DEFAULT 0,
                opened_emails integer DEFAULT 0,
                clicked_emails integer DEFAULT 0,
                bounced_emails integer DEFAULT 0,
                subscribed integer DEFAULT 0,
                unsubscribed integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT outbox_time_series_records_id_foreign FOREIGN KEY (outbox_time_series_id) REFERENCES outbox_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $freqPartitionName = "obtsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF outbox_time_series_records FOR VALUES IN ('$freqCode')");
        }

        DB::statement('CREATE INDEX outbox_time_series_records_outbox_time_series_id_index ON outbox_time_series_records (outbox_time_series_id)');
        DB::statement('CREATE INDEX outbox_time_series_records_from_index ON outbox_time_series_records ("from")');
        DB::statement('CREATE INDEX outbox_time_series_records_to_index ON outbox_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_time_series_records');
    }
};
