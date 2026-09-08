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
        DB::table('outboxes')->whereIn('code', ['reorder_reminder', 'reorder_reminder_2nd', 'reorder_reminder_3rd'])->update([
            'state' => 'suspended',
            'is_applicable' => false,
        ]);
    }

    public function down(): void
    {
    }
};
