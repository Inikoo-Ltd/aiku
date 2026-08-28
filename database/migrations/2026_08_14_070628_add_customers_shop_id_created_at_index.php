<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS customers_shop_id_created_at_index ON customers (shop_id, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customers_shop_id_created_at_index');
    }
};
