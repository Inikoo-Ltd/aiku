<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 12:58:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_child_webpages_sub_type_sub_department')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->dropColumn('number_child_webpages_sub_type_sub_department');
        });
    }
};
