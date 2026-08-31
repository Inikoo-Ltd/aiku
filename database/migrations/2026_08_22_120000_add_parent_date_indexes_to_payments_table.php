<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 12:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Period-filtered payment method reports read payments by organisation or shop and date.
 * Built concurrently and idempotently so it can be pre-created on production by hand without
 * the deploy migration failing or locking the table.
 */
return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS payments_organisation_id_date_index ON payments (organisation_id, date)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS payments_shop_id_date_index ON payments (shop_id, date)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS payments_organisation_id_date_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS payments_shop_id_date_index');
    }
};
