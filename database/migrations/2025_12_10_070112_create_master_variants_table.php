<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Dec 2025 21:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('master_variants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->unsignedInteger('master_shop_id')->nullable();
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->nullOnDelete();
            $table->unsignedInteger('master_family_id')->nullable();
            $table->foreign('master_family_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->unsignedInteger('master_sub_department_id')->nullable();
            $table->foreign('master_sub_department_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->unsignedInteger('master_department_id')->nullable();
            $table->foreign('master_department_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->string('code')->index();

            $table->unsignedInteger('leader_id')->nullable();
            $table->foreign('leader_id')->references('id')->on('master_assets')->nullOnDelete();
            $table->unsignedInteger('number_minions')->default(0);

            $table->unsignedInteger('number_dimensions')->default(0);
            $table->unsignedInteger('number_used_slots')->default(0);
            $table->unsignedInteger('number_used_slots_for_sale')->default(0);



            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_variants');
    }
};
