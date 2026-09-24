<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Sep 2026 09:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('ALTER TABLE dispatched_emails ADD COLUMN IF NOT EXISTS ses_id varchar(80)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS dispatched_emails_ses_id_index ON dispatched_emails (ses_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS dispatched_emails_ses_id_index');
        DB::statement('ALTER TABLE dispatched_emails DROP COLUMN IF EXISTS ses_id');
    }
};
