<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * A sister organisation buys from a shop through exactly one customer account, the one its
     * invoices land in the Partners category from. A second account for the same organisation
     * splits its history and balance, so it is refused here rather than cleaned up afterwards.
     */
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX customers_shop_id_as_organisation_id_unique
            ON customers (shop_id, as_organisation_id)
            WHERE as_organisation_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customers_shop_id_as_organisation_id_unique');
    }
};
