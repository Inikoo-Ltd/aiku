<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 02:09:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('master_product_category_time_series_records');

        DB::statement('
            CREATE TABLE master_product_category_time_series_records (
                id bigserial NOT NULL,
                type bpchar(1) NOT NULL,
                frequency bpchar(1) NOT NULL,
                "period" varchar(10) NOT NULL,
                master_product_category_time_series_id int4 NOT NULL,
                sales numeric(16,2) DEFAULT 0.00,
                sales_org_currency numeric(16,2) DEFAULT 0.00,
                sales_grp_currency numeric(16,2) DEFAULT 0.00,
                invoices int4 DEFAULT 0,
                refunds int4 DEFAULT 0,
                orders int4 DEFAULT 0,
                customers_invoiced int4 DEFAULT 0,
                "from" date,
                "to" date,
                created_at timestamptz,
                updated_at timestamptz,
        
                CONSTRAINT master_product_category_time_series_records_pkey PRIMARY KEY (id, type, frequency),
                CONSTRAINT f_master_product_category_time_seri FOREIGN KEY (master_product_category_time_series_id) REFERENCES master_product_category_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) PARTITION BY LIST (type);
        ');

        $types = [
            'D' => 'dept',
            'S' => 'sub_dept',
            'F' => 'family',
        ];

        $frequencies = [
            'D' => 'daily',
            'W' => 'weekly',
            'M' => 'monthly',
            'Q' => 'quarterly',
            'Y' => 'yearly',
        ];

        foreach ($types as $typeCode => $typeLabel) {
            $typePartitionName = "mpctr_{$typeLabel}";
            DB::statement("CREATE TABLE $typePartitionName PARTITION OF master_product_category_time_series_records FOR VALUES IN ('$typeCode') PARTITION BY LIST (frequency)");

            foreach ($frequencies as $freqCode => $freqLabel) {
                $partitionName = "mpctr_{$typeLabel}_{$freqLabel}";
                DB::statement("CREATE TABLE $partitionName PARTITION OF $typePartitionName FOR VALUES IN ('$freqCode')");
            }
        }


        DB::statement('CREATE UNIQUE INDEX mpts_records_series_id_period_unique ON master_product_category_time_series_records (master_product_category_time_series_id, "period","type","frequency")');
        DB::statement('CREATE INDEX master_product_category_time_series_records_series_id_index ON master_product_category_time_series_records (master_product_category_time_series_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_category_time_series_records');
    }
};
