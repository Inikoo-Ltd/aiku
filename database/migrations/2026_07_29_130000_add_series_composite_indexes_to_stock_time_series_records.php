<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS stsr_series_from_idx ON stock_time_series_records (stock_time_series_id, "from")');
        DB::statement('CREATE INDEX IF NOT EXISTS stsr_series_to_idx ON stock_time_series_records (stock_time_series_id, "to")');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stsr_series_from_idx');
        DB::statement('DROP INDEX IF EXISTS stsr_series_to_idx');
    }
};
