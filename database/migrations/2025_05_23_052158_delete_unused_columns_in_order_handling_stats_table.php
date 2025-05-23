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
        $pickingStates          = ['queued', 'picking', 'picking_blocked'];
        $pickingCompletedFields = ['done_today'];

        Schema::table('shop_order_handling_stats', function (Blueprint $table) use ($pickingStates, $pickingCompletedFields) {
            foreach ($pickingStates as $state) {
                $table->dropColumn('number_pickings_state_'.$state);
            }

            foreach ($pickingCompletedFields as $state) {
                $table->dropColumn('number_pickings_'.$state);
            }
        });

        Schema::table('organisation_order_handling_stats', function (Blueprint $table) use ($pickingStates, $pickingCompletedFields) {
            foreach ($pickingStates as $state) {
                $table->dropColumn('number_pickings_state_'.$state);
            }

            foreach ($pickingCompletedFields as $state) {
                $table->dropColumn('number_pickings_'.$state);
            }
        });

        Schema::table('group_order_handling_stats', function (Blueprint $table) use ($pickingStates, $pickingCompletedFields) {
            foreach ($pickingStates as $state) {
                $table->dropColumn('number_pickings_state_'.$state);
            }

            foreach ($pickingCompletedFields as $state) {
                $table->dropColumn('number_pickings_'.$state);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $pickingStates          = ['queued', 'picking', 'picking_blocked'];
        $pickingCompletedFields = ['done_today'];

        Schema::table('shop_order_handling_stats', function (Blueprint $table) use ($pickingStates, $pickingCompletedFields) {
            foreach ($pickingStates as $state) {
                $table->unsignedInteger('number_pickings_state_'.$state)->default(0);
            }

            foreach ($pickingCompletedFields as $state) {
                $table->unsignedInteger('number_pickings_'.$state)->default(0);
            }
        });

        Schema::table('organisation_order_handling_stats', function (Blueprint $table) use ($pickingStates, $pickingCompletedFields) {
            foreach ($pickingStates as $state) {
                $table->unsignedInteger('number_pickings_state_'.$state)->default(0);
            }

            foreach ($pickingCompletedFields as $state) {
                $table->unsignedInteger('number_pickings_'.$state)->default(0);
            }
        });

        Schema::table('group_order_handling_stats', function (Blueprint $table) use ($pickingStates, $pickingCompletedFields) {
            foreach ($pickingStates as $state) {
                $table->unsignedInteger('number_pickings_state_'.$state)->default(0);
            }

            foreach ($pickingCompletedFields as $state) {
                $table->unsignedInteger('number_pickings_'.$state)->default(0);
            }
        });
    }
};
