<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Mar 2026 15:54:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_mismatched_master_products_active')->default(0);
            $table->unsignedSmallInteger('number_mismatched_master_products_inactive')->default(0);
            $table->unsignedSmallInteger('number_mismatched_master_families_active')->default(0);
            $table->unsignedSmallInteger('number_mismatched_master_families_inactive')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_mismatched_master_products_active',
                'number_mismatched_master_products_inactive',
                'number_mismatched_master_families_active',
                'number_mismatched_master_families_inactive',
            ]);
        });
    }
};
