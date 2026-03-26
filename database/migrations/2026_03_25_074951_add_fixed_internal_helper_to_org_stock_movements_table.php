<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Mar 2026 15:50:47 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->boolean('fixed_internal_helper')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropColumn('fixed_internal_helper');
        });
    }
};
