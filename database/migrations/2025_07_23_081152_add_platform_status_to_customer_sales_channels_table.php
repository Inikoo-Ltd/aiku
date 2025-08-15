<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 09:13:57 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->boolean('can_connect_to_platform')->default(false);
            $table->boolean('exist_in_platform')->default(false);
            $table->boolean('platform_status')->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropColumn('platform_status');
            $table->dropColumn('exist_in_platform');
            $table->dropColumn('can_connect_to_platform');
        });
    }
};
