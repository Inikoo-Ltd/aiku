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
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $table->integer('number_pallet_returns_pallet_state_dispatched_today')->default(0);
            $table->integer('number_pallet_returns_items_state_dispatched_today')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_pallet_returns_pallet_state_dispatched_today',
                'number_pallet_returns_items_state_dispatched_today',
            ]);
        });
    }
};
