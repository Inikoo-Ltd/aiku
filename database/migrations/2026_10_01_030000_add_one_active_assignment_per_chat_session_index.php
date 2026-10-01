<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 15:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS chat_assignments_one_active_per_session_index ON chat_assignments (chat_session_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS meta_chat_assignments_one_active_per_session_index ON meta_chat_assignments (meta_chat_session_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS meta_chat_assignments_one_active_per_session_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS chat_assignments_one_active_per_session_index');
    }
};
