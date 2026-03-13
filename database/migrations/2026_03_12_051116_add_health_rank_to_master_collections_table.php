<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->string('health_rank')->nullable()->default(null)->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->dropColumn('health_rank');
        });
    }
};
