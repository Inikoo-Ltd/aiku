<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pallet_delivery_stats', function (Blueprint $table) {            
            $table->integer('number_pallets_state_not_picked')->default(0);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pallet_delivery_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_pallets_state_not_picked'
            ]);
        });
    }
};
