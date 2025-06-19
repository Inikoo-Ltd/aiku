<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 15:11:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_families_no_department')->default(0);
            $table->unsignedInteger('number_products_no_family')->default(0);
        });
        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_families_no_department')->default(0);
            $table->unsignedInteger('number_products_no_family')->default(0);
        });
        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_families_no_department')->default(0);
            $table->unsignedInteger('number_products_no_family')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn('number_families_no_department');
            $table->dropColumn('number_products_no_family');
        });
        Schema::table('organisation_catalogue_stats', function (Blueprint $table) {
            $table->dropColumn('number_families_no_department');
            $table->dropColumn('number_products_no_family');
        });
        Schema::table('group_catalogue_stats', function (Blueprint $table) {
            $table->dropColumn('number_families_no_department');
            $table->dropColumn('number_products_no_family');
        });
    }
};
