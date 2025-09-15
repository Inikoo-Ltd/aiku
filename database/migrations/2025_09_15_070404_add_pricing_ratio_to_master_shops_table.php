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
        Schema::table('master_shops', function (Blueprint $table) {
            $table->decimal('cost_price_ratio', 16, 3)->default(2);
            $table->decimal('price_rrp_ratio', 16, 3)->default(2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->dropColumn('cost_price_ratio');
            $table->dropColumn('price_rrp_ratio');
        });
    }
};
