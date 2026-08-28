<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * A scanned barcode has to name one org stock within the organisation, live rows only,
     * a discontinued sku must not hold a barcode hostage.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('create unique index org_stocks_organisation_barcode_unique on org_stocks (organisation_id, barcode) where barcode is not null and deleted_at is null');
    }

    /**
     * @return void
     */
    public function down()
    {
        DB::statement('drop index if exists org_stocks_organisation_barcode_unique');
    }
};
