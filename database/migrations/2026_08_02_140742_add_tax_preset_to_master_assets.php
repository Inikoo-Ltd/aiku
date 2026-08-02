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
        Schema::table('master_assets', function (Blueprint $table) {
            $table->string('tax_preset')->nullable()->default('standard')->index()
                ->comment('Named tax preset this master follows (standard, food, ...); null is a custom map; tax_category holds the expansion the money path reads');
        });
    }

    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('tax_preset');
        });
    }
};
