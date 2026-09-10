<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("create unique index manufacture_task_sessions_one_open_per_user on manufacture_task_sessions (user_id) where state = 'open'");
    }

    public function down(): void
    {
        DB::statement('drop index if exists manufacture_task_sessions_one_open_per_user');
    }
};
