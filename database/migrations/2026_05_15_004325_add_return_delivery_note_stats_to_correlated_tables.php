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
        $columns = [
            'number_return_delivery_notes',
            'number_return_delivery_notes_state_received',
            'number_return_delivery_notes_state_returning',
            'number_return_delivery_notes_state_returned',
            'number_return_delivery_notes_state_done',
            'number_return_delivery_notes_state_cancelled',
        ];

        Schema::table('group_procurement_stats', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->unsignedInteger($column)->default(0);
            }
        });

        Schema::table('organisation_procurement_stats', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->unsignedInteger($column)->default(0);
            }
        });

        Schema::table('warehouse_stats', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->unsignedInteger($column)->default(0);
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->unsignedInteger($column)->default(0);
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

        $columns = [
            'number_return_delivery_notes',
            'number_return_delivery_notes_state_received',
            'number_return_delivery_notes_state_returning',
            'number_return_delivery_notes_state_returned',
            'number_return_delivery_notes_state_done',
            'number_return_delivery_notes_state_cancelled',
        ];

        Schema::table('group_procurement_stats', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });

        Schema::table('organisation_procurement_stats', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });

        Schema::table('warehouse_stats', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });

        Schema::table('shop_stats', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
