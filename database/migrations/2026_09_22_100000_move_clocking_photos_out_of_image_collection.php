<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('media')
            ->where('model_type', 'Clocking')
            ->where('collection_name', 'image')
            ->update(['collection_name' => 'clocking_photo']);
    }

    public function down(): void
    {
        DB::table('media')
            ->where('model_type', 'Clocking')
            ->where('collection_name', 'clocking_photo')
            ->update(['collection_name' => 'image']);
    }
};
