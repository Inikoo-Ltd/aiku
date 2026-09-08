<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 08:39:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->string('units_review')->nullable()->index();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('units_review')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('units_review');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('units_review');
        });
    }
};
