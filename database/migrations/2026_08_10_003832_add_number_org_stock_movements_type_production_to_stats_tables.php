<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('number_org_stock_movements_type_production')->default(0);
        });

        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('number_org_stock_movements_type_production')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn('number_org_stock_movements_type_production');
        });

        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->dropColumn('number_org_stock_movements_type_production');
        });
    }
};
