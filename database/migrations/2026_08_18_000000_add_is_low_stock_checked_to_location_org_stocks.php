<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->boolean('is_low_stock_checked')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->dropColumn('is_low_stock_checked');
        });
    }
};
