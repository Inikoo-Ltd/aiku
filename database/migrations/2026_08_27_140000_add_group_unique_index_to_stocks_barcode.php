<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * A scan has to name exactly one thing, and the SKO barcode now lives on the stock and cascades
     * to its org stocks, so two stocks in a group answering to one barcode is corruption rather
     * than a preference. Until now nothing but a validation rule in UpdateOrgStock said so, which
     * left every other write path - repair commands, imports, tinker - free to break it.
     *
     * Partial, because a barcode is optional: Postgres treats NULLs as distinct in a plain unique
     * index, but soft deleted stocks would still collide with the live ones without the predicate.
     *
     * org_stocks deliberately gets no equivalent: its rows share their stock's barcode by design,
     * so uniqueness there belongs to the stock they hang off, not to the row.
     */
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX stocks_group_id_barcode_unique
            ON stocks (group_id, barcode)
            WHERE barcode IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stocks_group_id_barcode_unique');
    }
};
