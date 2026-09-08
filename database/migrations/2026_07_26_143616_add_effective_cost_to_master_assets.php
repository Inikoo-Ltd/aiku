<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 14:36:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->decimal('effective_cost', 16, 4)->nullable()->comment('stock-weighted avg cost across organisations, group currency, per outer');
        });
    }

    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('effective_cost');
        });
    }
};
