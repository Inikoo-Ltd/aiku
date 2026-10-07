<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->unsignedInteger('top_customer_id')->nullable();
            $table->decimal('top_customer_dispatch_share', 5, 4)->nullable();
            $table->decimal('top_customer_dispatched_12m', 16, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->dropColumn(['top_customer_id', 'top_customer_dispatch_share', 'top_customer_dispatched_12m']);
        });
    }
};
