<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Step one of retiring these columns. Nothing reads them, and the code no longer writes
     * them, but the drop itself has to wait for a later deploy: migrations run before the new
     * release goes live, so dropping them here would leave the running release writing to
     * columns that no longer exist.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE model_has_payments ALTER COLUMN amount SET DEFAULT 0');
        DB::statement('ALTER TABLE model_has_payments ALTER COLUMN amount DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE model_has_payments SET amount = 0 WHERE amount IS NULL');
        DB::statement('ALTER TABLE model_has_payments ALTER COLUMN amount SET NOT NULL');
        DB::statement('ALTER TABLE model_has_payments ALTER COLUMN amount DROP DEFAULT');
    }
};
