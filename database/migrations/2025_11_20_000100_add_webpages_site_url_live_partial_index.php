<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Nov 2025 00:07:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    // Using plain SQL to create a partial composite index tailored to the hot path:
    // SELECT id FROM webpages WHERE website_id = ? AND url = ? AND state = 'live' AND deleted_at IS NULL

    public function up(): void
    {
        // Note: Partial indexes and functional expressions are not supported via the Schema builder.
        // We keep it simple and rely on a partial index on (website_id, url) for live, non-deleted rows.
        DB::statement(
            "CREATE INDEX IF NOT EXISTS webpages_site_url_live_idx\n".
            "ON webpages (website_id, url) INCLUDE (id)\n".
            "WHERE state = 'live' AND deleted_at IS NULL;"
        );

    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS webpages_site_url_live_idx;");
    }
};
