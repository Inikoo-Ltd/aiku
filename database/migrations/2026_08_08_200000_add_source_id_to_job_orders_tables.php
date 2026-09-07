<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('source_id')->nullable()->unique();
        });
        Schema::table('job_order_items', function (Blueprint $table) {
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('source_id');
        });
        Schema::table('job_order_items', function (Blueprint $table) {
            $table->dropColumn('source_id');
        });
    }
};
