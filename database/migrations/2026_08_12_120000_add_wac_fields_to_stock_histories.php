<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->float('wac_per_sku')->nullable();
            $table->decimal('org_stock_wac_value', 16)->nullable();
            $table->decimal('grp_stock_wac_value', 16)->nullable();
        });

        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->decimal('org_stock_wac_value', 16)->nullable();
            $table->decimal('grp_stock_wac_value', 16)->nullable();
        });

        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->decimal('org_stock_wac_value', 16)->nullable();
            $table->decimal('grp_stock_wac_value', 16)->nullable();
        });

        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->decimal('grp_stock_wac_value', 16)->nullable();
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->date('wac_calculations_start_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->dropColumn(['wac_per_sku', 'org_stock_wac_value', 'grp_stock_wac_value']);
        });
        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->dropColumn(['org_stock_wac_value', 'grp_stock_wac_value']);
        });
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->dropColumn(['org_stock_wac_value', 'grp_stock_wac_value']);
        });
        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->dropColumn('grp_stock_wac_value');
        });
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('wac_calculations_start_date');
        });
    }
};
