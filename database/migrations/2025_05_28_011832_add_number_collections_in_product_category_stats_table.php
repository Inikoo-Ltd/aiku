<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 May 2025 10:15:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_collections')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->dropColumn('number_collections');
        });
    }
};
