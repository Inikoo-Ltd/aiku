<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // Create unique indexes that ignore soft-deleted records
            DB::statement('CREATE UNIQUE INDEX idx_prospects_shop_email_unique ON prospects(shop_id, email) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX idx_prospects_shop_phone_unique ON prospects(shop_id, phone) WHERE deleted_at IS NULL');
        });
    }


    public function down(): void
    {
        if (Schema::hasTable('prospects')) {
            if (Schema::hasIndex('prospects', 'idx_prospects_shop_email_unique')) {
                DB::statement('DROP INDEX IF EXISTS idx_prospects_shop_email_unique');
            }

            if (Schema::hasIndex('prospects', 'idx_prospects_shop_phone_unique')) {
                DB::statement('DROP INDEX IF EXISTS idx_prospects_shop_phone_unique');
            }
        }
    }
};
