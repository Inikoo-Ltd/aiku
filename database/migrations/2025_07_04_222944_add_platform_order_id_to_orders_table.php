<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 23:29:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('platform_order_id')->nullable()->index();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('platform_transaction_id')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('platform_order_id');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('platform_transaction_id');
        });
    }
};
