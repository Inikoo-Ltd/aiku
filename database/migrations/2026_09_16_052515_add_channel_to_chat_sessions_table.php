<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->string('channel', 16)->default('website')->index()->after('shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
