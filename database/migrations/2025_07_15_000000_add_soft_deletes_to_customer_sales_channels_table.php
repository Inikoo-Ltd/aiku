<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Jul 2025 22:01:51 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dateTimeTz('closed_at')->nullable();
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropColumn('closed_at');
            $table->dropSoftDeletes();
        });
    }
};
