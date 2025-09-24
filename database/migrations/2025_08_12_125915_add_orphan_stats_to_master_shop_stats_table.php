<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_master_families_no_master_department')->default(0);
            $table->unsignedInteger('number_master_products_no_master_family')->default(0);
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_master_families_no_master_department')->default(0);
            $table->unsignedInteger('number_master_products_no_master_family')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn('number_master_families_no_master_department');
            $table->dropColumn('number_master_products_no_master_family');
        });

        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $table->dropColumn('number_master_families_no_master_department');
            $table->dropColumn('number_master_products_no_master_family');
        });
    }
};
