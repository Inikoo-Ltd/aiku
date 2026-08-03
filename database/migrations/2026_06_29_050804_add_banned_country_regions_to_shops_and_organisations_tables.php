<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 13:14:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->jsonb('banned_country_regions')->default('{}');
        });
        Schema::table('organisations', function (Blueprint $table) {
            $table->jsonb('banned_country_regions')->default('{}');
        });
    }


    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('banned_country_regions');
        });
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('banned_country_regions');
        });
    }
};
