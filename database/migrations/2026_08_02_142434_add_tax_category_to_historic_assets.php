<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('historic_assets', function (Blueprint $table) {
            $table->jsonb('tax_category')->nullable()
                ->comment('Tax override map frozen at creation, like price; null on rows predating this column, which fall back to the live master map');
        });
    }

    public function down(): void
    {
        Schema::table('historic_assets', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });
    }
};
