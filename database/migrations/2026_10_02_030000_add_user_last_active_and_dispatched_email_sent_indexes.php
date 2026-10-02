<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 11:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS user_requests_user_id_date_index ON user_requests (user_id, date)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS dispatched_emails_outbox_id_sent_at_index ON dispatched_emails (outbox_id, sent_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS dispatched_emails_outbox_id_sent_at_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS user_requests_user_id_date_index');
    }
};
