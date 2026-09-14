<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('users')->whereRaw("jsonb_exists(settings, 'stale_orders_days')")->update(['settings' => DB::raw("settings - 'stale_orders_days'")]);
    }

    public function down(): void
    {
    }
};
