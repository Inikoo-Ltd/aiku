<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jan 2026 23:43:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {

            $table->unsignedSmallInteger('master_shop_id')->index()->nullable();
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->nullOnDelete();

            $table->unsignedInteger('master_department_id')->index()->nullable();
            $table->foreign('master_department_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->unsignedInteger('master_sub_department_id')->index()->nullable();
            $table->foreign('master_sub_department_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->unsignedInteger('master_family_id')->index()->nullable();
            $table->foreign('master_family_id')->references('id')->on('master_product_categories')->nullOnDelete();

            $table->unsignedInteger('master_asset_id')->index()->nullable();
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->nullOnDelete();


        });
    }


    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'master_asset_id',
                'master_department_id',
                'master_sub_department_id',
                'master_family_id',
                'master_shop_id',
            ]);
        });
    }
};
