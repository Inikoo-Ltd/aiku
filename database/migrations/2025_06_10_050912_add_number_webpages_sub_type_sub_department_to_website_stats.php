<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 16:22:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('website_stats', 'number_webpages_sub_type_sub_department')) {
                $table->unsignedSmallInteger('number_webpages_sub_type_sub_department')->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            if (Schema::hasColumn('website_stats', 'number_webpages_sub_type_sub_department')) {
                $table->dropColumn('number_webpages_sub_type_sub_department');
            }
        });
    }
};
