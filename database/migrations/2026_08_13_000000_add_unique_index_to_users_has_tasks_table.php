<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX users_has_tasks_user_task_unique
            ON users_has_tasks (user_id, task_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_has_tasks_user_task_unique');
    }
};
