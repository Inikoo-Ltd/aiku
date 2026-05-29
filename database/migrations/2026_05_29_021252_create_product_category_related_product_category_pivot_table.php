<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category_has_related_product_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_category_id')->index();
            $table->foreign('product_category_id', 'pc_hrpc_product_category_fk')->references('id')->on('product_categories')->cascadeOnDelete();
            $table->unsignedInteger('related_product_category_id')->index();
            $table->foreign('related_product_category_id', 'pc_hrpc_related_product_category_fk')->references('id')->on('product_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->index()->default(0);
            $table->timestampsTz();
            $table->unique(['product_category_id', 'related_product_category_id'], 'pc_hrpc_product_category_product_category_unique');
        });

        Schema::create('master_product_category_has_related_product_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('master_product_category_id')->index();
            $table->foreign('master_product_category_id', 'mpc_hrpc_master_product_category_fk')->references('id')->on('master_product_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('related_master_product_category_id')->index();
            $table->foreign('related_master_product_category_id', 'mpc_hrpc_related_product_category_fk')->references('id')->on('master_product_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->index()->default(0);
            $table->timestampsTz();
            $table->unique(['master_product_category_id', 'related_master_product_category_id'], 'mpc_hrpc_master_product_category_product_category_unique');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('product_category_has_related_product_categories');
        Schema::dropIfExists('master_product_category_has_related_product_categories');
    }
};
