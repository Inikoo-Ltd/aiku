<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 03:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS org_stock_histories_org_stock_id_date_index ON org_stock_histories (org_stock_id, date)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS org_stock_histories_org_stock_id_date_index');
    }
};
