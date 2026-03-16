<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE trade_unit_time_series_records (
                id bigserial,
                trade_unit_time_series_id integer NOT NULL,
                frequency char(1) NOT NULL,
                sales_external numeric(16,2) DEFAULT 0,
                sales_org_currency_external numeric(16,2) DEFAULT 0,
                sales_grp_currency_external numeric(16,2) DEFAULT 0,
                sales_internal numeric(16,2) DEFAULT 0,
                sales_org_currency_internal numeric(16,2) DEFAULT 0,
                sales_grp_currency_internal numeric(16,2) DEFAULT 0,
                lost_revenue numeric(16,2) DEFAULT 0,
                lost_revenue_org_currency numeric(16,2) DEFAULT 0,
                lost_revenue_grp_currency numeric(16,2) DEFAULT 0,
                invoices integer DEFAULT 0,
                refunds integer DEFAULT 0,
                orders integer DEFAULT 0,
                customers_invoiced integer DEFAULT 0,
                "from" timestamptz,
                "to" timestamptz,
                period varchar(255),
                created_at timestamptz,
                updated_at timestamptz,
                PRIMARY KEY (id, frequency),
                CONSTRAINT trade_unit_time_series_records_id_foreign FOREIGN KEY (trade_unit_time_series_id) REFERENCES trade_unit_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $freqPartitionName = "tutsr_$freqLabel";
            DB::statement("CREATE TABLE $freqPartitionName PARTITION OF trade_unit_time_series_records FOR VALUES IN ('$freqCode')");

        }

        DB::statement('CREATE INDEX trade_unit_time_series_records_trade_unit_time_series_id_index ON trade_unit_time_series_records (trade_unit_time_series_id)');
        DB::statement('CREATE INDEX trade_unit_time_series_records_from_index ON trade_unit_time_series_records ("from")');
        DB::statement('CREATE INDEX trade_unit_time_series_records_to_index ON trade_unit_time_series_records ("to")');
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_unit_time_series_records');
    }
};
