<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Dec 2025 12:00:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->index()->comment('for search purposes');
            $table->jsonb('shipping_data')->default('{}')->comment('for UI purposes');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->index()->comment('for search purposes');
            $table->jsonb('shipping_data')->default('{}')->comment('for UI purposes');
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking_number');
            $table->dropColumn('shipping_data');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('tracking_number');
            $table->dropColumn('shipping_data');
        });
    }
};
