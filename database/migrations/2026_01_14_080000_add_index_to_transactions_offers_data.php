<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("CREATE INDEX transactions_offers_data_o_o_index ON transactions ((offers_data->'o'->>'o'))");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS transactions_offers_data_o_o_index");
    }
};
