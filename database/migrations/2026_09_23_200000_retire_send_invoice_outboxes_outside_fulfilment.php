<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('outboxes')
            ->where('code', 'send_invoice_to_customer')
            ->whereIn('shop_id', DB::table('shops')->where('type', '!=', 'fulfilment')->select('id'))
            ->update([
                'state'         => 'suspended',
                'is_applicable' => false,
            ]);
    }

    public function down(): void
    {
    }
};
