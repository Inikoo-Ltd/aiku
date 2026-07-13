<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('outboxes')
            ->where('code', 'abandoned_cart')
            ->where('model_type', 'Mailshot')
            ->update(['model_type' => 'EmailOngoingRun']);
    }

    public function down(): void
    {
        DB::table('outboxes')
            ->where('code', 'abandoned_cart')
            ->where('model_type', 'EmailOngoingRun')
            ->update(['model_type' => 'Mailshot']);
    }
};
