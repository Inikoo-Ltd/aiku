<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 12 May 2026 09:53:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('mailshot_time_series_records');

        DB::statement('
            CREATE TABLE mailshot_time_series_records (
                id bigserial,
                mailshot_time_series_id integer NOT NULL,
                frequency char(1) NOT NULL,
                number_dispatched_emails integer DEFAULT 0,
                number_dispatched_emails_state_ready integer DEFAULT 0,
                number_dispatched_emails_state_sent_to_provider integer DEFAULT 0,
                number_dispatched_emails_state_error integer DEFAULT 0,
                number_dispatched_emails_state_rejected_by_provider integer DEFAULT 0,
                number_dispatched_emails_state_sent integer DEFAULT 0,
                number_dispatched_emails_state_delivered integer DEFAULT 0,
                number_dispatched_emails_state_hard_bounce integer DEFAULT 0,
                number_dispatched_emails_state_soft_bounce integer DEFAULT 0,
                number_dispatched_emails_state_opened integer DEFAULT 0,
                number_dispatched_emails_state_clicked integer DEFAULT 0,
                number_dispatched_emails_state_spam integer DEFAULT 0,
                number_dispatched_emails_state_unsubscribed integer DEFAULT 0,
                number_provoked_unsubscribe integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT mailshot_time_series_records_mailshot_time_series_id_foreign FOREIGN KEY (mailshot_time_series_id) REFERENCES mailshot_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) PARTITION BY LIST (frequency);
        ');

        $frequencies = [
            'D' => 'daily',
            'W' => 'weekly',
            'M' => 'monthly',
        ];

        foreach ($frequencies as $freqCode => $freqLabel) {
            $freqPartitionName = "mtsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF mailshot_time_series_records FOR VALUES IN ('$freqCode')");
        }

        DB::statement('CREATE INDEX mailshot_time_series_records_mailshot_time_series_id_index ON mailshot_time_series_records (mailshot_time_series_id)');
        DB::statement('CREATE INDEX mailshot_time_series_records_from_index ON mailshot_time_series_records ("from")');
        DB::statement('CREATE INDEX mailshot_time_series_records_to_index ON mailshot_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('mailshot_time_series_records');
    }
};
