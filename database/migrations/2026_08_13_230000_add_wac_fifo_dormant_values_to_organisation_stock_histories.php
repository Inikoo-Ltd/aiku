<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->decimal('value_dormant_stock_1y_wac', 16)->nullable();
            $table->decimal('value_dormant_stock_1y_fifo', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->dropColumn(['value_dormant_stock_1y_wac', 'value_dormant_stock_1y_fifo']);
        });
    }
};
