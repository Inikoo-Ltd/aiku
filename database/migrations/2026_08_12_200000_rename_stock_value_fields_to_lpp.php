<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->renameColumn('org_stock_value', 'org_stock_lpp_value');
            $table->renameColumn('grp_stock_value', 'grp_stock_lpp_value');
            $table->renameColumn('value_per_sku', 'lpp_per_sku');
        });

        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->renameColumn('org_stock_value', 'org_stock_lpp_value');
            $table->renameColumn('grp_stock_value', 'grp_stock_lpp_value');
        });

        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->renameColumn('org_stock_value', 'org_stock_lpp_value');
            $table->renameColumn('grp_stock_value', 'grp_stock_lpp_value');
        });

        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->renameColumn('grp_stock_value', 'grp_stock_lpp_value');
        });
    }

    public function down()
    {
        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->renameColumn('org_stock_lpp_value', 'org_stock_value');
            $table->renameColumn('grp_stock_lpp_value', 'grp_stock_value');
            $table->renameColumn('lpp_per_sku', 'value_per_sku');
        });

        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->renameColumn('org_stock_lpp_value', 'org_stock_value');
            $table->renameColumn('grp_stock_lpp_value', 'grp_stock_value');
        });

        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->renameColumn('org_stock_lpp_value', 'org_stock_value');
            $table->renameColumn('grp_stock_lpp_value', 'grp_stock_value');
        });

        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->renameColumn('grp_stock_lpp_value', 'grp_stock_value');
        });
    }
};
