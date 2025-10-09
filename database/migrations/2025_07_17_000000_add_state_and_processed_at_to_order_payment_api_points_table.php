<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Jul 2025 00:11:17 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('order_payment_api_points', function (Blueprint $table) {
            $table->string('state')->index()->nullable();
            $table->dateTimeTz('processed_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('order_payment_api_points', function (Blueprint $table) {
            $table->dropColumn('state');
            $table->dropColumn('processed_at');
        });
    }
};
