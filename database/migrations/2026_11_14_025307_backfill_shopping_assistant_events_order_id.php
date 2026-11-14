<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('update shopping_assistant_events set order_id = transactions.order_id from transactions where transactions.id = shopping_assistant_events.transaction_id and shopping_assistant_events.order_id is null');
    }

    public function down(): void
    {
    }
};
