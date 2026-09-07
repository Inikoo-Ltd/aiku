<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('preferred_shippings', function (Blueprint $table) {
            $table->string('trade_scope')->default('b2b')->index();
        });
    }

    public function down(): void
    {
        Schema::table('preferred_shippings', function (Blueprint $table) {
            $table->dropColumn('trade_scope');
        });
    }
};
