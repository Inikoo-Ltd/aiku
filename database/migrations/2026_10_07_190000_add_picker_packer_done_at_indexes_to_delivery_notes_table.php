<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 19:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS delivery_notes_picker_user_id_picked_at_index ON delivery_notes (picker_user_id, picked_at) WHERE picker_user_id IS NOT NULL');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS delivery_notes_packer_user_id_packed_at_index ON delivery_notes (packer_user_id, packed_at) WHERE packer_user_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS delivery_notes_packer_user_id_packed_at_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS delivery_notes_picker_user_id_picked_at_index');
    }
};
