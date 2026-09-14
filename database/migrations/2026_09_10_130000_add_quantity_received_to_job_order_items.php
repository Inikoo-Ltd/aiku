<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('job_order_items', function (Blueprint $table) {
            $table->decimal('quantity_received', 16, 3)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('job_order_items', function (Blueprint $table) {
            $table->dropColumn('quantity_received');
        });
    }
};
