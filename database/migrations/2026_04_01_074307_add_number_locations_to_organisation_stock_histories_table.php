<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:44:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_locations')->default(0);
        });

        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('organisation_stock_history_id')->nullable()->index();
            $table->foreign('organisation_stock_history_id')->references('id')->on('organisation_stock_histories');
        });
    }

    public function down(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->dropColumn('number_locations');
        });
        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->dropColumn('organisation_stock_history_id');
        });
    }
};
