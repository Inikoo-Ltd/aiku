<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Oct 2025 12:30:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->unsignedSmallInteger('origin_country_id')->nullable()->index();
            $table->foreign('origin_country_id')->references('id')->on('countries');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('origin_country_id')->nullable()->index();
            $table->foreign('origin_country_id')->references('id')->on('countries');
        });
    }

    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropForeign(['origin_country_id']);
            $table->dropColumn('origin_country_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['origin_country_id']);
            $table->dropColumn('origin_country_id');
        });
    }
};
