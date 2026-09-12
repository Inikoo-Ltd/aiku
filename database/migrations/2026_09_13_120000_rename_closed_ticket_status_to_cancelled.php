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
        DB::table('tickets')->where('status', 'closed')->update(['status' => 'cancelled']);
        DB::table('tickets')->whereRaw("lower(data->>'jira_status') in ('no reply', 'no relpy')")->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        DB::table('tickets')->where('status', 'cancelled')->whereRaw("lower(data->>'jira_status') in ('no reply', 'no relpy')")->update(['status' => 'open']);
        DB::table('tickets')->where('status', 'cancelled')->update(['status' => 'closed']);
    }
};
