<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE supplier_products ADD CONSTRAINT supplier_products_units_per_carton_positive CHECK (units_per_carton IS NULL OR units_per_carton > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE supplier_products DROP CONSTRAINT IF EXISTS supplier_products_units_per_carton_positive');
    }
};
