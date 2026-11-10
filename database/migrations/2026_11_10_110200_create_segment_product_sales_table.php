<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('segment_product_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('shop_id');
            $table->string('level');
            $table->string('shop_type')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('region', 2)->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('family_id')->nullable();
            $table->integer('segment_customers');
            $table->integer('customers');
            $table->integer('repeat_customers');
            $table->decimal('revenue', 16, 2);
            $table->index(['shop_id', 'level', 'shop_type', 'country_code', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_product_sales');
    }
};
