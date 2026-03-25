<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Mar 2026 14:42:10 Central Indonesia Time, Plane Bali-KL
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->string('fixed')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropColumn('fixed');
        });
    }
};
