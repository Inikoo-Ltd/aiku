<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 15:51:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("update chat_ai_drafts set time_lost = null, time_lost_seconds = null where time_lost is not null and decided_at < '2026-11-19 00:00:00+00'");
    }

    public function down(): void
    {
    }
};
