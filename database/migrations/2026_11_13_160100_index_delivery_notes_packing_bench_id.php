<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 18:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS delivery_notes_packing_bench_id_index ON delivery_notes (packing_bench_id)');
        DB::statement('ALTER TABLE delivery_notes ADD CONSTRAINT delivery_notes_packing_bench_id_foreign FOREIGN KEY (packing_bench_id) REFERENCES packing_benches (id) ON DELETE SET NULL NOT VALID');
        DB::statement('ALTER TABLE delivery_notes VALIDATE CONSTRAINT delivery_notes_packing_bench_id_foreign');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE delivery_notes DROP CONSTRAINT IF EXISTS delivery_notes_packing_bench_id_foreign');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS delivery_notes_packing_bench_id_index');
    }
};
