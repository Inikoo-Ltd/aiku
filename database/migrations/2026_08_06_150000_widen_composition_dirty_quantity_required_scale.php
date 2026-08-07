<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * It held three decimals against a required quantity that holds six, so an item picked
     * in twelfths stored 0.083 next to a required 0.083333, never compared equal and stayed
     * flagged for a change that had not happened.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE delivery_note_items ALTER COLUMN composition_dirty_quantity_required TYPE numeric(16,6)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE delivery_note_items ALTER COLUMN composition_dirty_quantity_required TYPE numeric(16,3)');
    }
};
