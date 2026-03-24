<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Mar 2026 19:38:56 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->boolean('failed_geo_location')->nullable()->default(null);
        });
    }


    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('failed_geo_location');
        });
    }
};
