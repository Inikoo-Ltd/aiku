<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("
            update bank_transactions
            set counterparty_account = nullif(trim(data->'movement'->'column2'->>'value'), '')
                || coalesce('/' || nullif(trim(data->'movement'->'column3'->>'value'), ''), '')
            where counterparty_account is null
              and data->'movement'->'column2'->>'value' is not null
        ");
    }

    public function down(): void
    {
    }
};
