<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 16 Nov 2024 10:16:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('org_stock_has_org_supplier_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_has_supplier_product_id')->index();
            $table->foreign('stock_has_supplier_product_id')->references('id')->on('stock_has_supplier_products');
            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');
            $table->unsignedBigInteger('org_supplier_product_id')->nullable();
            $table->foreign('org_supplier_product_id')->references('id')->on('org_supplier_products');
            $table->boolean('status')->default(true)->index();
            $table->unsignedSmallInteger('local_priority')->default(0)->index();
            $table->timestampsTz();
            $table->unique(['org_stock_id', 'org_supplier_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_stock_has_org_supplier_products');
    }
};
