<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Apr 2026 17:49:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('group_stock_histories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->date('date')->index();
            $table->decimal('grp_stock_value', 16)->default(0);
            $table->decimal('grp_stock_commercial_value', 16)->default(0);
            $table->decimal('grp_value_dormant_stock_1y', 16)->default(0);
            $table->float('percentage_value_dormant_stock_1y', 16)->default(0);


            $table->unsignedInteger('number_stocks')->default(0);
            $table->unsignedInteger('number_org_stocks_no_stock')->default(0);
            $table->unsignedInteger('number_stocks_org_stocks_no_stock')->default(0)->comment('Number of stocks plus org stocks with no stock relationship');

            $table->unsignedInteger('number_org_stocks')->default(0);
            $table->unsignedInteger('number_out_of_stock_org_stocks')->default(0);
            $table->unsignedInteger('number_location_org_stocks')->default(0);
            $table->unsignedInteger('number_locations')->default(0);


            $table->boolean('is_week')->index()->default(false);
            $table->boolean('is_month')->index()->default(false);
            $table->boolean('is_year')->index()->default(false);


            $table->timestampsTz();
            $table->unique(['group_id', 'date']);
        });

        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_stock_history_id')->nullable()->index();
            $table->foreign('group_stock_history_id')->references('id')->on('group_stock_histories')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->dropForeign(['group_stock_history_id']);
            $table->dropColumn('group_stock_history_id');
        });

        Schema::dropIfExists('organisation_stock_histories');
    }
};
