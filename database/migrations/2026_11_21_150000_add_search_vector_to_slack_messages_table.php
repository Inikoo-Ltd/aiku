<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '10s'");
        DB::statement('ALTER TABLE slack_messages ADD COLUMN IF NOT EXISTS search_vector tsvector');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE slack_messages DROP COLUMN IF EXISTS search_vector');
    }
};
