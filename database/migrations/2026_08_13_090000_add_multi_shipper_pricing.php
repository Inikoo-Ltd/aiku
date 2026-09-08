<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->jsonb('shippers_price')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('shipper_id')->nullable()->index();
            $table->foreign('shipper_id')->references('id')->on('shippers')->nullOnDelete();
            $table->boolean('is_shipper_locked')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipper_id']);
            $table->dropColumn(['shipper_id', 'is_shipper_locked']);
        });

        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropColumn('shippers_price');
        });
    }
};
