<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 16 Nov 2024 09:24:01 Central Indonesia Time, Sanur, Bali, Indonesia
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
        Schema::create('stock_has_supplier_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('stock_id')->index();
            $table->foreign('stock_id')->references('id')->on('stocks');
            $table->unsignedBigInteger('supplier_product_id')->nullable();
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products');
            $table->boolean('available')->default(true)->index();
            $table->unsignedSmallInteger('priority')->default(0)->index();
            $table->timestampsTz();
            $table->unique(['stock_id', 'supplier_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_has_supplier_products');
    }
};
