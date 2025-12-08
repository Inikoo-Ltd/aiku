<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Sept 2025 20:36:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dateTimeTz('out_of_stock_since')->nullable();
            $table->dateTimeTz('back_in_stock_since')->nullable();
            $table->dateTimeTz('estimated_back_in_stock_at')->nullable();
            $table->unsignedInteger('estimated_to_be_delivered_quantity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['out_of_stock_since', 'back_in_stock_since', 'estimated_back_in_stock_at', 'estimated_to_be_delivered_quantity']);
        });
    }
};
