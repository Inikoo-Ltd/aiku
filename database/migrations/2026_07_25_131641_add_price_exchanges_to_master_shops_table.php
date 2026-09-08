<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2026 13:16:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->jsonb('price_exchanges')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->dropColumn('price_exchanges');
        });
    }
};
