<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 02:10:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_category_time_series', function (Blueprint $table) {
            $table->string('type')->index();
        });
        Schema::table('master_product_category_time_series', function (Blueprint $table) {
            $table->string('type')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('product_category_time_series', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        Schema::table('master_product_category_time_series', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
