<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Jan 2026 13:39:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('discretionary_offer', 6, 3)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->jsonb('discretionary_offers_data')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('discretionary_offer');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discretionary_offers_data');
        });
    }
};
