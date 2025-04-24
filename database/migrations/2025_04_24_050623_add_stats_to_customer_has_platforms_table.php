<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Apr 2025 14:25:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasOrderingStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasOrderingStats;

    public function up(): void
    {
        Schema::table('customer_has_platforms', function (Blueprint $table) {
            $table->unsignedInteger('number_customer_clients')->default(0);
            $table->unsignedInteger('number_portfolios')->default(0);
            $this->ordersStatsFields($table);
        });
    }


    public function down(): void
    {
        Schema::table('customer_has_platforms', function (Blueprint $table) {
            $table->dropColumn('number_customer_clients');
            $table->dropColumn('number_portfolios');
            $this->ordersStatsFieldsDown($table);
        });
    }
};
