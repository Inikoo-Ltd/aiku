<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    // NEEDED FOR THIS ERROR ON SENTRY (AIKU-18JS) OTHERWISE STATS WOULD ALWAYS FAIL DUE TO COLUMN TYPE (INT 2 BYTE)
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->integer('number_stocks')->change();
            $table->integer('number_org_stocks_no_stock')->change();
            $table->integer('number_stocks_org_stocks_no_stock')->change();
            $table->integer('number_org_stocks')->change();
            $table->integer('number_out_of_stock_org_stocks')->change();
            $table->integer('number_location_org_stocks')->change();
        });

        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->integer('number_org_stocks')->change();
            $table->integer('number_out_of_stock_org_stocks')->change();
            $table->integer('number_location_org_stocks')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->smallInteger('number_stocks')->change();
            $table->smallInteger('number_org_stocks_no_stock')->change();
            $table->smallInteger('number_stocks_org_stocks_no_stock')->change();
            $table->smallInteger('number_org_stocks')->change();
            $table->smallInteger('number_out_of_stock_org_stocks')->change();
            $table->smallInteger('number_location_org_stocks')->change();
        });

        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->smallInteger('number_org_stocks')->change();
            $table->smallInteger('number_out_of_stock_org_stocks')->change();
            $table->smallInteger('number_location_org_stocks')->change();
        });
    }
};
