<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Jul 2025 18:06:05 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('unit_price', 12)->nullable()->comment('price per unit');
        });
    }


    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
