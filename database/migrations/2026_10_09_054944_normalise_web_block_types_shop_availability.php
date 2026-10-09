<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('web_block_types')
            ->whereRaw("shop_availability = '{}'::jsonb")
            ->update(['shop_availability' => '[]']);
    }


    public function down(): void
    {
    }
};
