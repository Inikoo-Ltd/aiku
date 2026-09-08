<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Items already carry composition_dirty_at, but a picker only sees it after opening the
     * note. The count is rolled up so a note whose packing changed under it can be marked in
     * the lists as well.
     */
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_items_composition_dirty')->default(0)->index();
        });

        DB::statement("
            UPDATE delivery_notes dn
            SET number_items_composition_dirty = counts.dirty
            FROM (
                SELECT delivery_note_id, count(*) AS dirty
                FROM delivery_note_items
                WHERE composition_dirty_at IS NOT NULL
                GROUP BY delivery_note_id
            ) counts
            WHERE counts.delivery_note_id = dn.id
        ");
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('number_items_composition_dirty');
        });
    }
};
