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
            $table->decimal('sales_12m', 16, 2)->nullable();
            $table->unsignedSmallInteger('sales_rank_12m')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->dropColumn(['sales_12m', 'sales_rank_12m']);
        });
    }
};
