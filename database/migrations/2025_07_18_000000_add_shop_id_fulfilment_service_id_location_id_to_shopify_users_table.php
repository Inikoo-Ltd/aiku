<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Jul 2025 14:53:50 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shopify_users', function (Blueprint $table) {
            $table->string('shopify_shop_id')->nullable();
            $table->string('shopify_fulfilment_service_id')->nullable();
            $table->string('shopify_location_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_users', function (Blueprint $table) {
            $table->dropColumn(
                [
                    'shopify_shop_id',
                    'shopify_fulfilment_service_id',
                    'shopify_location_id'
                ]
            );
        });
    }
};
