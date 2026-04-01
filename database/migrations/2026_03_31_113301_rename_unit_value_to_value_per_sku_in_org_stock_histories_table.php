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
        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->renameColumn('unit_value', 'value_per_sku');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->renameColumn('value_per_sku', 'unit_value');
        });
    }
};
