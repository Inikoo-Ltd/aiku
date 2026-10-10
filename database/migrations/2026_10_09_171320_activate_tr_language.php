<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('languages')->where('code', 'tr')->update(['status' => '1', 'native_name' => 'Türkçe']);
    }

    public function down(): void
    {
        DB::table('languages')->where('code', 'tr')->update(['status' => '0', 'native_name' => 'Turkish']);
    }
};
