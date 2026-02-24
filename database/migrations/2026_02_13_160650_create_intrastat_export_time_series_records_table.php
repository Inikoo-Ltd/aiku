<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:06:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE intrastat_export_time_series_records (
                id bigserial,
                intrastat_export_time_series_id integer NOT NULL,
                organisation_id integer NOT NULL,
                frequency char(1) NOT NULL,
                quantity numeric(16,2) DEFAULT 0,
                value_org_currency numeric(16,2) DEFAULT 0,
                weight bigint DEFAULT 0,
                delivery_notes_count integer DEFAULT 0,
                products_count integer DEFAULT 0,
                invoices_count integer DEFAULT 0,
                delivery_note_type varchar(20),
                partner_tax_numbers jsonb,
                valid_tax_numbers_count integer DEFAULT 0,
                invalid_tax_numbers_count integer DEFAULT 0,
                mode_of_transport varchar(10),
                delivery_terms varchar(10),
                nature_of_transaction varchar(10),
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT intrastat_export_tsr_intrastat_export_ts_id_foreign
                    FOREIGN KEY (intrastat_export_time_series_id)
                    REFERENCES intrastat_export_time_series(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT intrastat_export_tsr_organisation_id_foreign
                    FOREIGN KEY (organisation_id)
                    REFERENCES organisations(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT intrastat_export_tsr_unique
                    UNIQUE (intrastat_export_time_series_id, frequency, period)
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
            $freqPartitionName = "ietsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF intrastat_export_time_series_records FOR VALUES IN ('$freqCode')");
        }

        DB::statement('CREATE INDEX ietsr_intrastat_export_ts_org_period_idx
            ON intrastat_export_time_series_records (intrastat_export_time_series_id, organisation_id, period)');

        DB::statement('CREATE INDEX ietsr_from_to_idx
            ON intrastat_export_time_series_records ("from", "to")');

        DB::statement('CREATE INDEX ietsr_period_idx
            ON intrastat_export_time_series_records (period)');

        DB::statement('CREATE INDEX ietsr_intrastat_export_time_series_id_idx
            ON intrastat_export_time_series_records (intrastat_export_time_series_id)');

        DB::statement('CREATE INDEX ietsr_delivery_note_type_idx
            ON intrastat_export_time_series_records (delivery_note_type)');

        DB::statement('CREATE INDEX ietsr_invoices_count_idx
            ON intrastat_export_time_series_records (invoices_count)');
    }

    public function down(): void
    {
        Schema::dropIfExists('intrastat_export_time_series_records');
    }
};
