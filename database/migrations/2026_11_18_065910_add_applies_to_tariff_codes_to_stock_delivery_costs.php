<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stock_delivery_costs', function (Blueprint $table) {
            $table->jsonb('applies_to_tariff_codes')->nullable();
            $table->jsonb('applies_to_item_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_delivery_costs', function (Blueprint $table) {
            $table->dropColumn(['applies_to_tariff_codes', 'applies_to_item_ids']);
        });
    }
};
