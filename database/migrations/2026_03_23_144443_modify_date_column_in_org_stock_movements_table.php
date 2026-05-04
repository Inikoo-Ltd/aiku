<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Mar 2026 22:45:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE org_stock_movements ALTER COLUMN date TYPE TIMESTAMPTZ(6)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE org_stock_movements ALTER COLUMN date TYPE TIMESTAMPTZ(0)');
    }
};
