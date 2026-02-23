<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Feb 2026 19:06:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_shipping_by_external')->default(false);
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('is_shipping_by_external')->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_shipping_by_external');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('is_shipping_by_external');
        });
    }
};
