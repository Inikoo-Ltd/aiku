<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_packaging_id_date_index ON orders (packaging_id, date) INCLUDE (customer_id, state) WHERE packaging_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_packaging_id_date_index');
    }
};
