<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 02:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('product_category_time_series_records');

        DB::statement('
            CREATE TABLE product_category_time_series_records (
                id bigserial NOT NULL,
                type bpchar(1) NOT NULL,
                frequency bpchar(1) NOT NULL,
                "period" varchar(10) NOT NULL,
                product_category_time_series_id int4 NOT NULL,
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
        
                CONSTRAINT product_category_time_series_records_pkey PRIMARY KEY (id, type, frequency),
                CONSTRAINT product_category_time_series_records_product_category_time_seri FOREIGN KEY (product_category_time_series_id) REFERENCES product_category_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE
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
            $typePartitionName = "pctr_{$typeLabel}";
            DB::statement("CREATE TABLE $typePartitionName PARTITION OF product_category_time_series_records FOR VALUES IN ('$typeCode') PARTITION BY LIST (frequency)");

            foreach ($frequencies as $freqCode => $freqLabel) {
                $partitionName = "pctr_{$typeLabel}_{$freqLabel}";
                DB::statement("CREATE TABLE $partitionName PARTITION OF $typePartitionName FOR VALUES IN ('$freqCode')");
            }
        }


        DB::statement('CREATE UNIQUE INDEX product_category_time_series_records_series_id_period_unique ON product_category_time_series_records (product_category_time_series_id, "period","type","frequency")');
        DB::statement('CREATE INDEX product_category_time_series_records_series_id_index ON product_category_time_series_records (product_category_time_series_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_time_series_records');

        Schema::create('product_category_time_series_records', function ($table) {
            $table->id();
            $table->foreignId('product_category_time_series_id')->constrained('product_category_time_series')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('sales')->nullable();
            $table->decimal('sales_org_currency')->nullable();
            $table->decimal('sales_grp_currency')->nullable();
            $table->integer('invoices')->nullable();
            $table->integer('refunds')->nullable();
            $table->integer('orders')->nullable();
            $table->integer('delivery_notes')->nullable();
            $table->integer('customers_invoiced')->nullable();
            $table->timestampTz('from')->nullable()->index();
            $table->timestampTz('to')->nullable()->index();
            $table->timestampsTz();
            $table->char('type', 1)->index();
            $table->char('frequency', 1)->index();
        });
    }
};
