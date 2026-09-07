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
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->string('name')->nullable()->index()->comment('short label used on export invoices, curated by staff');
        });
    }

    public function down(): void
    {
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
