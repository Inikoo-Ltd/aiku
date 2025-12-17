<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Dec 2025 21:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->unsignedInteger('family_id')->nullable();
            $table->foreign('family_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->unsignedInteger('sub_department_id')->nullable();
            $table->foreign('sub_department_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->unsignedInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->string('code')->index();

            $table->unsignedInteger('leader_id')->nullable();
            $table->foreign('leader_id')->references('id')->on('assets')->nullOnDelete();
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
        Schema::dropIfExists('variants');
    }
};
