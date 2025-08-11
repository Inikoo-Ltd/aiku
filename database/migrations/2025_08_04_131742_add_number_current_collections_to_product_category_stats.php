<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 15:19:11 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('master_product_category_stats', 'number_current_collections')) {
                $table->unsignedSmallInteger('number_current_collections')->default(0)->comment('state=active+discontinuing');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            if (Schema::hasColumn('master_product_category_stats', 'number_current_collections')) {
                $table->dropColumn('number_current_collections');
            }
        });
    }
};
