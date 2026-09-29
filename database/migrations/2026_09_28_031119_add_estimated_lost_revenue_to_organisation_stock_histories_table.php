<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->decimal('estimated_lost_revenue_org_currency', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->dropColumn('estimated_lost_revenue_org_currency');
        });
    }
};
