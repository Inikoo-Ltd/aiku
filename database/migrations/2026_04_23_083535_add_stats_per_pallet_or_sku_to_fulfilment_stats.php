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
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $table->integer('number_pallet_returns_items')->default(0);
            $table->integer('number_pallet_returns_items_state_in_process')->default(0);
            $table->integer('number_pallet_returns_items_state_submitted')->default(0);
            $table->integer('number_pallet_returns_items_state_confirmed')->default(0);
            $table->integer('number_pallet_returns_items_state_picking')->default(0);
            $table->integer('number_pallet_returns_items_state_picked')->default(0);
            $table->integer('number_pallet_returns_items_state_dispatched')->default(0);
            $table->integer('number_pallet_returns_items_state_consolidated')->default(0);
            $table->integer('number_pallet_returns_items_state_cancel')->default(0);

            $table->integer('number_pallet_returns_pallet')->default(0);
            $table->integer('number_pallet_returns_pallet_state_in_process')->default(0);
            $table->integer('number_pallet_returns_pallet_state_submitted')->default(0);
            $table->integer('number_pallet_returns_pallet_state_confirmed')->default(0);
            $table->integer('number_pallet_returns_pallet_state_picking')->default(0);
            $table->integer('number_pallet_returns_pallet_state_picked')->default(0);
            $table->integer('number_pallet_returns_pallet_state_dispatched')->default(0);
            $table->integer('number_pallet_returns_pallet_state_consolidated')->default(0);
            $table->integer('number_pallet_returns_pallet_state_cancel')->default(0);
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
            $table->dropColumns([
                'number_pallet_returns_items',
                'number_pallet_returns_items_state_in_process',
                'number_pallet_returns_items_state_submitted',
                'number_pallet_returns_items_state_confirmed',
                'number_pallet_returns_items_state_picking',
                'number_pallet_returns_items_state_picked',
                'number_pallet_returns_items_state_dispatched',
                'number_pallet_returns_items_state_consolidated',
                'number_pallet_returns_items_state_cancel',
                'number_pallet_returns_pallet',
                'number_pallet_returns_pallet_state_in_process',
                'number_pallet_returns_pallet_state_submitted',
                'number_pallet_returns_pallet_state_confirmed',
                'number_pallet_returns_pallet_state_picking',
                'number_pallet_returns_pallet_state_picked',
                'number_pallet_returns_pallet_state_dispatched',
                'number_pallet_returns_pallet_state_consolidated',
                'number_pallet_returns_pallet_state_cancel',
            ]);
        });
    }
};
