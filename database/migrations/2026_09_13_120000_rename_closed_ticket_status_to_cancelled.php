<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * HELP-2637 was answered by its reporter, HELP-2123, HELP-2261 and HELP-1703 were replied to
     * and only carry the Jira no reply label by mistake, so they keep the state they are in.
     */
    private const array REPLIED = ['HELP-2123', 'HELP-2261', 'HELP-1703'];

    public function up(): void
    {
        DB::table('tickets')->where('status', 'closed')->update(['status' => 'cancelled']);

        DB::table('tickets')
            ->whereRaw("lower(data->>'jira_status') in ('no reply', 'no relpy')")
            ->whereNotIn('reference', array_merge(self::REPLIED, ['HELP-2637']))
            ->update(['status' => 'cancelled']);

        DB::table('tickets')->where('reference', 'HELP-2637')->update(['status' => 'resolved', 'resolved_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        DB::table('tickets')->where('status', 'cancelled')->whereRaw("lower(data->>'jira_status') in ('no reply', 'no relpy')")->update(['status' => 'open']);
        DB::table('tickets')->where('status', 'cancelled')->update(['status' => 'closed']);
        DB::table('tickets')->where('reference', 'HELP-2637')->update(['status' => 'open', 'resolved_at' => null]);
    }
};
